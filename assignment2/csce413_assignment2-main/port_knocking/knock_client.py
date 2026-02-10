"""
Usage:
    python3 knock_client.py --target 172.20.0.40 --sequence 1234,5678,9012 --check
"""

import argparse
import socket
import time
import sys
from typing import List, Optional


DEFAULT_KNOCK_SEQUENCE: List[int] = [1234, 5678, 9012]
DEFAULT_PROTECTED_PORT: int = 2222
DEFAULT_DELAY: float = 0.5


def send_knock(target: str, port: int, delay: float) -> None:
    """
    Sends a single UDP "knock" packet to the target host and port.

    Args:
        target (str): The IP address or hostname of the target.
        port (int): The UDP port to knock on.
        delay (float): Seconds to wait after sending the knock.
    """
    print(f"Knocking on {target}:{port} with UDP")
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as sock:
            sock.sendto(b"knock", (target, port))
    except Exception as e:
        print(f"Error sending knock to {port}: {e}")
    time.sleep(delay)


def perform_knock_sequence(target: str, sequence: List[int], delay: float) -> None:
    """
    Sends the full ordered sequence of knocks to the target.

    Args:
        target (str): The IP address or hostname of the target.
        sequence (List[int]): The ordered list of ports to knock.
        delay (float): Seconds to wait between each knock.
    """
    print(f"Starting knock sequence to {target}: {sequence}")
    for port in sequence:
        send_knock(target, port, delay)
    print("Sequence complete.")


def check_protected_port(target: str, protected_port: int) -> bool:
    """
    Attempts to establish a TCP connection to the protected port to verify access.

    Args:
        target (str): The IP address or hostname of the target.
        protected_port (int): The TCP port to verify.

    Returns:
        bool: True if the port is open and accessible, False otherwise.
    """
    print(f"Verifying protected port {protected_port} at {target}...")
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.settimeout(3.0)
            status = sock.connect_ex((target, protected_port))
            if status == 0:
                print(f"SUCCESS: Port {protected_port} is OPEN!")
                return True
            else:
                print(f"FAILED: Port {protected_port} is still CLOSED.")
                return False
    except Exception as e:
        print(f"Error verifying port {protected_port}: {e}")
        return False


def parse_args() -> argparse.Namespace:
    """
    Parses CLI arguments for the knock client.

    Returns:
        argparse.Namespace: The parsed command-line arguments.
    """
    parser = argparse.ArgumentParser(description="Port knocking client")
    parser.add_argument("--target", required=True, help="Target host or IP")
    parser.add_argument(
        "--sequence",
        default=",".join(str(port) for port in DEFAULT_KNOCK_SEQUENCE),
        help="Comma-separated knock ports",
    )
    parser.add_argument(
        "--protected-port",
        type=int,
        default=DEFAULT_PROTECTED_PORT,
        help="Protected service port",
    )
    parser.add_argument(
        "--delay",
        type=float,
        default=DEFAULT_DELAY,
        help="Delay between knocks in seconds",
    )
    parser.add_argument(
        "--check",
        action="store_true",
        help="Attempt connection to protected port after knocking",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    try:
        sequence = [int(port) for port in args.sequence.split(",")]
    except ValueError:
        raise SystemExit("Invalid sequence. Use comma-separated integers.")

    perform_knock_sequence(args.target, sequence, args.delay)

    if args.check:
        time.sleep(0.5)
        check_protected_port(args.target, args.protected_port)


if __name__ == "__main__":
    main()
