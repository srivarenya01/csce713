import socket
import sys
import struct
import time
import select
import threading
import json
import csv
import argparse
import ipaddress
import os
from concurrent.futures import ThreadPoolExecutor, as_completed

def calculate_checksum(data):
    """ Checksum Calculator """
    if len(data) % 2:
        data += b'\0'
    
    s = sum(struct.unpack(f"!{len(data)//2}H", data))
    
    while s >> 16:
        s = (s & 0xFFFF) + (s >> 16)
        
    return ~s & 0xFFFF

def create_icmp_packet(packet_id):
    """
    Create a manual ICMP Echo Request packet in network byte order.
    """
    header = struct.pack("!BBHHH", 8, 0, 0, packet_id, 1)
    data = struct.pack("!d", time.time())
    checksum = calculate_checksum(header + data)
    header = struct.pack("!BBHHH", 8, 0, checksum, packet_id, 1)
    return header + data

def ping(target, timeout=1):
    """
    Perform an ICMP ping using a raw socket.
    """
    try:
        icmp_proto = socket.getprotobyname("icmp")
        with socket.socket(socket.AF_INET, socket.SOCK_RAW, icmp_proto) as sock:
            sock.settimeout(timeout)
            packet_id = threading.get_ident() & 0xFFFF
            packet = create_icmp_packet(packet_id)
            
            sock.sendto(packet, (target, 1))
            
            start_time = time.time()
            while True:
                time_left = timeout - (time.time() - start_time)
                if time_left <= 0:
                    return False
                
                ready = select.select([sock], [], [], time_left)
                if not ready[0]:
                    return False
                
                recv_packet, addr = sock.recvfrom(1024)
                
                if addr[0] != target:
                    continue

                ihl = (recv_packet[0] & 0x0F) * 4
                icmp_header = recv_packet[ihl:ihl+8]
                
                if len(icmp_header) < 8:
                    continue

                icmp_type, icmp_code, icmp_checksum, packet_id_recv, sequence = struct.unpack("!BBHHH", icmp_header)
                
                if icmp_type == 0 and packet_id_recv == packet_id:
                    return True
    except PermissionError:
        print(f"[!] ICMP requires root privileges for {target}. Discovery limited.", file=sys.stderr)
        return None
    except Exception:
        return False

def scan_port(target, port, timeout=1.0):
    """
    Scan a single port and attempt banner grab.
    """
    result = {"port": port, "state": "closed", "banner": None, "latency": 0.0}
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.settimeout(timeout)
            start_time = time.time()
            status = sock.connect_ex((target, port))
            result["latency"] = time.time() - start_time
            
            if status == 0:
                result["state"] = "open"
                try:
                    sock.settimeout(0.5)
                    try:
                        data = sock.recv(1024)
                        if data:
                            result["banner"] = "".join(c for c in data.decode(errors='ignore') if c.isprintable() or c in '\n\r').strip()
                    except (socket.timeout, socket.error):
                        sock.settimeout(timeout)
                        sock.send(b"HEAD / HTTP/1.0\r\n\r\n") 
                        data = sock.recv(1024)
                        if data:
                            result["banner"] = data.decode(errors='ignore').splitlines()[0].strip()
                except Exception:
                    pass
    except Exception:
        pass
    return result


def scan_probe(target, port, timeout=1.0):
    """
    Worker for the global probe pool.
    """
    return target, scan_port(target, port, timeout)

def scan_host_discovery(target, timeout=1.0):
    """
    Discovery worker.
    """
    is_up = ping(target, timeout)
    return target, is_up

def parse_ports(port_str):
    """Parse port string: '80,443' or '1-1024'."""
    ports = []
    if not port_str:
        return []
    for part in port_str.split(','):
        if '-' in part:
            start, end = map(int, part.split('-'))
            ports.extend(range(start, end + 1))
        else:
            ports.append(int(part))
    return sorted(list(set(ports)))

def expand_target(target_str):
    """Expand a single target string (IP or CIDR)."""
    if '/' in target_str:
        try:
            network = ipaddress.ip_network(target_str, strict=False)
            return [str(ip) for ip in network.hosts()]
        except ValueError:
            return [target_str]
    return [target_str]

def parse_targets_concurrently(target_str_list):
    """Concurrency for CIDR expansion as requested."""
    all_targets = []
    with ThreadPoolExecutor() as executor:
        futures = {executor.submit(expand_target, t): t for t in target_str_list}
        for future in as_completed(futures):
            all_targets.extend(future.result())
    return all_targets

def output_results(results, skipped_count, method, output_file):
    """Infers format from extension and handles skipped count."""
    results.sort(key=lambda x: ipaddress.ip_address(x["target"]))
    active_results = [h for h in results if h["state"] == "up"]

    if not output_file:
        if method == "icmp":
            print("Hosts on the given target are:")
            for host in active_results:
                print(host["target"])
            return

        for host in active_results:
            print(f"\nResults for {host['target']} ({host['state']}):")
            if host["open_ports"]:
                for p in host["open_ports"]:
                    banner = f" ({p['banner']})" if p["banner"] else ""
                    latency = f" [{p['latency']:.4f}s]"
                    print(f"  Port {p['port']}: {p['state']}{banner}{latency}")
            else:
                print("  No open ports found.")
        
        if skipped_count > 0:
            print(f"\n[*] Skipped {skipped_count} hosts (not responding).")
        return

    ext = os.path.splitext(output_file)[1].lower()
    
    if ext == ".json":
        data = json.dumps({"active_hosts": active_results, "skipped_count": skipped_count}, indent=4)
    elif ext == ".csv":
        import io
        output = io.StringIO()
        writer = csv.writer(output)
        writer.writerow(["Target", "State", "Port", "Service", "Latency"])
        for host in active_results:
            if not host["open_ports"]:
                 writer.writerow([host["target"], host["state"], "N/A", "", ""])
            for p in host["open_ports"]:
                writer.writerow([host["target"], host["state"], p["port"], p["banner"] or "", f"{p['latency']:.4f}s"])
        writer.writerow(["Summary", f"Skipped: {skipped_count}", "", ""])
        data = output.getvalue()
    elif ext == ".txt":
        lines = []
        if method == "icmp":
            lines.append("Hosts on the given target are:")
            for host in active_results:
                lines.append(host["target"])
        else:
            for host in active_results:
                lines.append(f"\n{host['target']} ({host['state']}):")
                if host["open_ports"]:
                    for p in host["open_ports"]:
                        banner = f" ({p['banner']})" if p["banner"] else ""
                        latency = f" [{p['latency']:.4f}s]"
                        lines.append(f"  Port {p['port']}: {p['state']}{banner}{latency}")
                else:
                    lines.append("  No open ports found.")
        
        if skipped_count > 0:
            lines.append(f"\n[*] Skipped {skipped_count} hosts (not responding).")
        data = "\n".join(lines)
    else:
        print(f"[!] Unsupported extension '{ext}'. Using TXT format.")
        output_results(results, skipped_count, method, output_file + ".txt")
        return

    with open(output_file, 'w') as f:
        f.write(data)
    print(f"[+] Results saved to {output_file}")

def main():
    parser = argparse.ArgumentParser(description="Professional Concurrent Port Scanner")
    parser.add_argument("target", nargs='+', help="Target IP address(es) or CIDR range(s)")
    parser.add_argument("--ports", help="Port range (default: 1-1024)")
    parser.add_argument("--method", choices=["tcp", "icmp", "auto"], default="auto", 
                        help="Scan method: icmp (discovery only), tcp (no discovery), auto (nmap-style)")
    parser.add_argument("--timeout", type=float, default=1.0, help="Timeout for connection in seconds (default: 1.0)")
    parser.add_argument("--threads", type=int, default=100)
    parser.add_argument("--verbose", "-v", action="store_true", help="Enable verbose output")
    parser.add_argument("--output", help="Output file (format inferred from extension)")

    args = parser.parse_args()

    if not args.ports:
        args.ports = "1-1024"
    
    all_target_ips = parse_targets_concurrently(args.target)
    ports = parse_ports(args.ports)
    
    discovery_results = []
    active_targets = []
    skipped_count = 0

    do_discovery = (args.method == "icmp") or (args.method == "auto" and len(all_target_ips) > 5)
    
    if do_discovery:
        print(f"[*] Starting ICMP discovery on {len(all_target_ips)} potential hosts...")
        total_discovery = len(all_target_ips)
        completed_discovery = 0
        with ThreadPoolExecutor(max_workers=min(total_discovery, args.threads)) as executor:
            futures = {executor.submit(scan_host_discovery, ip): ip for ip in all_target_ips}
            for future in as_completed(futures):
                ip, is_up = future.result()
                if is_up:
                    active_targets.append(ip)
                    discovery_results.append({"target": ip, "state": "up", "open_ports": []})
                    if args.verbose:
                        print(f"[+] Discovery: {ip} is up")
                else:
                    skipped_count += 1
                
                completed_discovery += 1
                if not args.verbose and (completed_discovery % 10 == 0 or completed_discovery == total_discovery):
                    print(f"[*] Discovery Progress: {completed_discovery}/{total_discovery} ({completed_discovery/total_discovery*100:.1f}%)", end='\r')
        
        print(f"\n[*] Discovery complete. Found {len(active_targets)} active hosts.")
    else:
        active_targets = all_target_ips
        for ip in active_targets:
            discovery_results.append({"target": ip, "state": "up", "open_ports": []})

    final_results = discovery_results
    
    if args.method != "icmp" and active_targets:
        print(f"[*] Proceeding to TCP scan on {len(active_targets)} active host(s)...")
        print(f"[*] Total probes to execute: {len(active_targets) * len(ports)}")
        
        results_map = {res["target"]: res for res in discovery_results if res["state"] == "up"}
        
        all_probes = [(ip, port) for ip in active_targets for port in ports]
        total_probes = len(all_probes)
        completed_probes = 0
        
        with ThreadPoolExecutor(max_workers=args.threads) as executor:
            batch_size = 20000
            for i in range(0, total_probes, batch_size):
                batch = all_probes[i:i + batch_size]
                futures = {executor.submit(scan_probe, ip, port, args.timeout): (ip, port) for ip, port in batch}
                
                for future in as_completed(futures):
                    target_ip, port_res = future.result()
                    if port_res["state"] == "open":
                        results_map[target_ip]["open_ports"].append(port_res)
                        if args.verbose:
                            banner_str = f" ({port_res['banner']})" if port_res["banner"] else ""
                            print(f"[+] Port Open: {target_ip}:{port_res['port']}{banner_str} [{port_res['latency']:.4f}s]")
                        
                    
                    completed_probes += 1
                    if not args.verbose and (completed_probes % 100 == 0 or completed_probes == total_probes):
                        print(f"[*] Progress: {completed_probes}/{total_probes} ({completed_probes/total_probes*100:.1f}%)", end='\r')
        
        print(f"\n[*] TCP scan complete.")

        for host_res in final_results:
            host_res["open_ports"].sort(key=lambda x: x["port"])

    final_output = [res for res in final_results if res["state"] == "up"]
    output_results(final_output, skipped_count, args.method, args.output)

if __name__ == "__main__":
    main()
