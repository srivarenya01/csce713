FROM ubuntu:22.04

RUN apt-get update && apt-get install -y --no-install-recommends \
    openssh-server \
    iptables \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir /var/run/sshd \
    && echo 'root:password123' | chpasswd \
    && sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config \
    && sed -i 's/UsePAM yes/UsePAM no/' /etc/ssh/sshd_config \
    && sed -i 's/#LogLevel INFO/LogLevel VERBOSE/' /etc/ssh/sshd_config

RUN mkdir -p /app/logs /home/admin \
    && echo "admin: supersecret\nroot: password123" > /home/admin/passwords.txt \
    && chown -R root:root /home/admin

RUN touch /root/.bash_history
RUN echo 'export HISTFILE=/root/.bash_history' >> /root/.bashrc \
    && echo 'export PROMPT_COMMAND="history -a; tail -n1 /root/.bash_history >> /app/logs/honeypot.log"' >> /root/.bashrc

RUN printf '#!/bin/bash\n\
    IP="$PAM_RHOST"\n\
    echo "$(date) CONNECTION from $IP user=$PAM_USER" >> /app/logs/honeypot.log\n\
    iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT 2>/dev/null\n\
    iptables -A OUTPUT -o lo -j ACCEPT 2>/dev/null\n\
    iptables -A OUTPUT -j DROP 2>/dev/null\n' > /usr/local/bin/on_login.sh && chmod +x /usr/local/bin/on_login.sh

RUN echo 'session optional pam_exec.so /usr/local/bin/on_login.sh' >> /etc/pam.d/sshd \
    && sed -i 's/UsePAM no/UsePAM yes/' /etc/ssh/sshd_config

RUN printf '#!/bin/bash\n\
    touch /app/logs/honeypot.log\n\
    tail -f /app/logs/honeypot.log &\n\
    /usr/sbin/sshd -D -e\n' > /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 22

CMD ["/entrypoint.sh"]
