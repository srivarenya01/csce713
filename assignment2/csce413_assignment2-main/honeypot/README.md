## SSH Honeypot

A Docker-native SSH honeypot that emulates a real SSH server to attract and log attacker activity. Built entirely with standard Linux tools — no Python dependencies.

### How It Works

- **OpenSSH** listens on port 22 (mapped to host port `2222`) with root login enabled and a weak password (`password123`).
- A **PAM session hook** (`on_login.sh`) fires on every login, recording the remote IP, username, and timestamp to `/var/log/honeypot.log`.
- **iptables rules** are applied on login to prevent the attacker from pivoting out of the container (egress locked to established/related + loopback only).
- **Bash history** commands are appended to the honeypot log via `PROMPT_COMMAND`, capturing every command an attacker runs.
- A bait file (`/home/admin/passwords.txt`) is planted to lure deeper interaction.

### Logs

All connection and command activity is written to `logs/honeypot.log` (bind-mounted from the container's `/app/logs`).

### Files

| File                | Purpose                                                                   |
| ------------------- | ------------------------------------------------------------------------- |
| `Dockerfile`        | Builds the honeypot image (Ubuntu 22.04 + OpenSSH + PAM hooks + iptables) |
| `entrypoint.sh`     | Starts rsyslog, sshd, and tails logs to stdout                            |
| `logs/honeypot.log` | Recorded connection metadata and attacker commands                        |
| `analysis.md`       | Summary of observed attacks and recommendations                           |

### Running

```bash
docker-compose up honeypot
```

The honeypot will be accessible on `localhost:2222`. All activity is logged to `logs/honeypot.log`.
