"""
Usage:
    sudo python3 knock_server.py --sequence 1234,5678,9012 --protected-port 2222
"""

import argparse
import logging
import socket
import time
import subprocess
import random
from typing import List, Dict, Tuple, Any, Set


ALERT_LEVEL: int = 60
logging.addLevelName(ALERT_LEVEL, "ALERT")

def log_alert(self, message, *args, **kws):
    if self.isEnabledFor(ALERT_LEVEL):
        self._log(ALERT_LEVEL, message, args, **kws)

logging.Logger.alert = log_alert


DEFAULT_KNOCK_SEQUENCE: List[int] = [1234, 5678, 9012]
DEFAULT_PROTECTED_PORT: int = 2222
DEFAULT_SEQUENCE_WINDOW: float = 10.0

client_states: Dict[str, Dict[str, Any]] = {}

def setup_logging() -> None:
    """Configures centralized logging for the server."""
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s - %(levelname)s - %(message)s",
        handlers=[logging.StreamHandler()],
    )

def execute_iptables(action: str, source_ip: str, port: int) -> bool:
    """
    Executes an iptables command to modify firewall rules.

    Args:
        action (str): The iptables action ('A' for add, 'D' for delete).
        source_ip (str): The IP address to allow/block.
        port (int): The target port to open/close.

    Returns:
        bool: True if the command executed successfully, False otherwise.
    """
    cmd = ["iptables", "-" + action, "INPUT"]
    if action == "I":
        cmd.append("1")
    cmd.extend(["-p", "tcp", "-s", source_ip, "--dport", str(port), "-j", "ACCEPT"])
    try:
        logging.info("Executing: %s", " ".join(cmd))
        subprocess.run(cmd, check=True)
        return True
    except subprocess.CalledProcessError as e:
        logging.error("Iptables error: %s", e)
        return False

def open_protected_port(source_ip: str, protected_port: int) -> None:
    """
    Opens the protected port for a specific source IP.

    Args:
        source_ip (str): The IP address that completed the valid sequence.
        protected_port (int): The port to open.
    """
    logging.info("VALID SEQUENCE from %s. Opening port %s", source_ip, protected_port)
    execute_iptables("I", source_ip, protected_port)

def close_protected_port(source_ip: str, protected_port: int) -> None:
    """
    Closes the protected port for a specific source IP.

    Args:
        source_ip (str): The IP address to block.
        protected_port (int): The port to close.
    """
    logging.info("Closing port %s for %s", protected_port, source_ip)
    execute_iptables("D", source_ip, protected_port)

def generate_decoys(sequence: List[int], count: int = 4) -> Set[int]:
    """
    Generates random decoy ports within the range of the sequence ports.

    Args:
        sequence (List[int]): The sequence ports to avoid.
        count (int): Number of decoys to generate.

    Returns:
        Set[int]: A set of unique decoy port numbers.
    """
    if not sequence:
        return set()
    
    min_p, max_p = min(sequence), max(sequence)
    seq_set = set(sequence)
    decoys = set()
    
    potential_range = list(range(min_p, max_p + 1))
    available_ports = [p for p in potential_range if p not in seq_set]
    
    if len(available_ports) < count:
        count = len(available_ports)
        
    if count > 0:
        decoys = set(random.sample(available_ports, count))
        
    return decoys

def listen_for_knocks(sequence: List[int], dummy_ports: Set[int], window_seconds: float, protected_port: int) -> None:
    """
    Main listener loop that monitors UDP ports for the knock sequence and decoy hits.

    Args:
        sequence (List[int]): The ordered list of ports that must be knocked.
        dummy_ports (Set[int]): Decoy ports that trigger alerts and reset progress.
        window_seconds (float): Max time allowed between knocks in the sequence.
        protected_port (int): The port to open upon success.
    """
    logger = logging.getLogger("KnockServer")
    logger.info("Listening for knocks (UDP): %s", sequence)
    if dummy_ports:
        logger.info("Decoy (dummy) ports active: %s", sorted(list(dummy_ports)))
    logger.info("Protected port: %s", protected_port)

    try:
        logging.info("Initializing firewall rules...")
        subprocess.run(["iptables", "-D", "INPUT", "-p", "tcp", "--dport", str(protected_port), "-j", "DROP"], stderr=subprocess.DEVNULL)
        subprocess.run(["iptables", "-A", "INPUT", "-p", "tcp", "--dport", str(protected_port), "-j", "DROP"], check=True)
        logging.info("Firewall configured: Port %d is now CLOSED by default.", protected_port)
    except Exception as e:
        logger.error("Failed to initialize firewall: %s", e)


    sockets: List[socket.socket] = []
    monitored_ports = set(sequence) | dummy_ports
    
    for port in monitored_ports:
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.bind(('0.0.0.0', port))
            s.setblocking(False)
            sockets.append(s)
            logger.info("Bound to port %d", port)
        except Exception as e:
            logger.error("Failed to bind to port %d: %s", port, e)

    logger.info("Server is active. Waiting for knocks...")
    
    while True:
        for s in sockets:
            try:
                data, addr = s.recvfrom(1024)
                source_ip: str = addr[0]
                knock_port: int = s.getsockname()[1]
                current_time: float = time.time()
                
                if knock_port in dummy_ports:
                    logger.alert("CRITICAL WARNING: Decoy port hit! Connection attempted on dummy port %d from %s.", knock_port, source_ip)
                    if source_ip in client_states:
                        client_states[source_ip]['progress'] = 0
                    continue

                logger.info("Knock received on %d from %s", knock_port, source_ip)
                
                state = client_states.get(source_ip, {'progress': 0, 'last_knock_time': 0.0})
                
                if state['progress'] > 0 and (current_time - state['last_knock_time'] > window_seconds):
                    logger.warning("Window timeout for %s. Resetting.", source_ip)
                    state['progress'] = 0
                
                expected_port = sequence[state['progress']]
                
                if knock_port == expected_port:
                    state['progress'] += 1
                    state['last_knock_time'] = current_time
                    logger.info("Progress for %s: %d/%d", source_ip, state['progress'], len(sequence))
                    
                    if state['progress'] == len(sequence):
                        open_protected_port(source_ip, protected_port)
                        state['progress'] = 0
                else:
                    logger.warning("Incorrect port %d from %s. Expected %d. Resetting.", knock_port, source_ip, expected_port)
                    state['progress'] = 0
                
                client_states[source_ip] = state
                
            except BlockingIOError:
                continue
            except Exception as e:
                logger.error("Error in listener loop: %s", e)
        
        time.sleep(0.1)

def parse_args() -> argparse.Namespace:
    """Parses command line arguments."""
    parser = argparse.ArgumentParser(description="Port knocking server")
    parser.add_argument("--sequence", default=",".join(str(port) for port in DEFAULT_KNOCK_SEQUENCE), help="Comma-separated knock ports")
    parser.add_argument("--protected-port", type=int, default=DEFAULT_PROTECTED_PORT, help="Protected service port")
    parser.add_argument("--window", type=float, default=DEFAULT_SEQUENCE_WINDOW, help="Seconds allowed to complete the sequence")
    return parser.parse_args()

def main() -> None:
    """Entry point for the knock server application."""
    args = parse_args()
    setup_logging()

    try:
        sequence = [int(port.strip()) for port in args.sequence.split(",")]
    except ValueError:
        raise SystemExit("Invalid sequence. Use comma-separated integers.")

    dummy_ports = generate_decoys(sequence, count=4)

    listen_for_knocks(sequence, dummy_ports, args.window, args.protected_port)

if __name__ == "__main__":
    main()
