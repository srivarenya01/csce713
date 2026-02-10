# Start rsyslog for auth.log
service rsyslog start

# Start SSH service in background
/usr/sbin/sshd -D &

# Create logs if missing
touch /var/log/auth.log /app/logs/honeypot.log

# Tail both logs to stdout
tail -f /var/log/auth.log /app/logs/honeypot.log
