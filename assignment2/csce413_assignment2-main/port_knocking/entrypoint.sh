#!/bin/bash
set -e

# Starting SSH service
echo "[*] Starting SSH Service on Port 2222..."
service ssh start

# Starting the Knock Server
echo "[*] Starting Knock Server..."
exec python3 knock_server.py
