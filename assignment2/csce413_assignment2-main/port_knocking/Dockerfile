FROM python:3.11-slim

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends iptables iproute2 openssh-server \
    && rm -rf /var/lib/apt/lists/*

# Configure SSH
RUN mkdir -p /var/run/sshd \
    && useradd -m -s /bin/bash knockuser \
    && echo "knockuser:knockpassword" | chpasswd \
    && sed -i 's/#Port 22/Port 2222/' /etc/ssh/sshd_config

COPY knock_server.py entrypoint.sh ./
RUN chmod +x entrypoint.sh

CMD ["./entrypoint.sh"]
