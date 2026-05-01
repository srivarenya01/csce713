#include <arpa/inet.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/ptrace.h>
#include <sys/user.h>
#include <sys/wait.h>
#include <unistd.h>

// Known client endpoint for OTP submission.
#define CLIENT_IP "127.0.0.1"
#define CLIENT_PORT 9001
#define PRINT_OTP_ADDR 0x401336UL

static int wait_for_stop(pid_t pid) {
    // Wait until traced process stops.
    int status = 0;
    if (waitpid(pid, &status, 0) < 0) {
        perror("waitpid"); // Failed to wait for the process to stop.
        return 0;
    }
    return 1; // Process stopped.
}

static int send_otp(int otp) {
    // Opening a TCP connection and submitting the current OTP.
    int sock = socket(AF_INET, SOCK_STREAM, 0);
    if (sock < 0) return 0; // Failed to create the socket.

    // Setting up the destination address.
    struct sockaddr_in dst;
    memset(&dst, 0, sizeof(dst)); 
    dst.sin_family = AF_INET;
    dst.sin_port = htons((unsigned short)CLIENT_PORT);
    if (inet_pton(AF_INET, CLIENT_IP, &dst.sin_addr) != 1) {
        // Invalid IP address.
        close(sock);
        return 0;
    }

    // Connecting to the destination.
    if (connect(sock, (struct sockaddr *)&dst, sizeof(dst)) < 0) {
        // Failed to connect to the destination.
        close(sock);
        return 0;
    }

    // Sending the OTP to the destination.
    char msg[8];
    sprintf(msg, "%06d\n", otp);
    (void)send(sock, msg, 7, 0);

    // Closing the socket.
    close(sock);

    // Successfully sent the OTP.
    return 1;
}

// Main function.
int main(int argc, char **argv) {
    // Checking the number of arguments.
    if (argc != 2) {
        fprintf(stderr, "Usage: %s <server_path>\n", argv[0]);
        return 1; // Invalid number of arguments.
    }

    // Server path from the arguments.
    char *server_path = argv[1];

    // Breakpoint address from objdump for this fixed lab binary.
    unsigned long bp_addr = PRINT_OTP_ADDR;

    // Spawn child server; parent becomes debugger.
    pid_t pid = fork();
    if (pid < 0) {
        perror("fork"); // Failed to fork the process.
        return 1;
    }

    // Child process.
    if (pid == 0) {
        // Allowing the parent to trace child process.
        if (ptrace(PTRACE_TRACEME, 0, NULL, NULL) < 0) _exit(1);

        // Executing the server binary.
        execl(server_path, server_path, (char *)NULL);
        _exit(1);
    }

    // Parent process.
    // Child process stops on exec, now setting software breakpoint.
    if (!wait_for_stop(pid)) return 1; // Failed to wait for the process to stop.

    // Reading original instruction bytes at print_otp entry.
    errno = 0;
    long original = ptrace(PTRACE_PEEKTEXT, pid, (void *)bp_addr, NULL);
    // Original instruction bytes.
    long word = original;
    // Failed to read the original instruction bytes.
    if (word == -1 && errno != 0) {
        perror("PTRACE_PEEKTEXT"); // Failed to read the original instruction bytes.
        return 1;
    }
    // Replace first byte with INT3 (0xCC).
    long with_breakpoint = (word & ~0xffL) | 0xcc;  // 0xcc is the INT3 opcode.
    if (ptrace(PTRACE_POKETEXT, pid, (void *)bp_addr, (void *)with_breakpoint) < 0) {
        perror("PTRACE_POKETEXT"); // Failed to replace the first byte with INT3.
        return 1;
    }

    // Printing helpful startup logs.
    printf("Spawned server PID %d\n", pid);
    printf("Breakpoint at 0x%lx\n", bp_addr);
    printf("Sending OTPs to %s:%d\n", CLIENT_IP, CLIENT_PORT);

    // Main loop.
    while (1) {
        // Continue child until next stop.
        if (ptrace(PTRACE_CONT, pid, NULL, NULL) < 0) {
            perror("PTRACE_CONT"); // Failed to continue the child process.
            return 1;
        }
        if (!wait_for_stop(pid)) return 1;

        // Reading registers from the child process.
        struct user_regs_struct regs;
        if (ptrace(PTRACE_GETREGS, pid, NULL, &regs) < 0) {
            perror("PTRACE_GETREGS"); // Failed to read the registers from the child process.
            return 1;
        }

        // SIGTRAP after INT3 means RIP points one byte after breakpoint.
        if ((unsigned long)(regs.rip - 1ULL) != bp_addr) {
            continue; // Ignoring unrelated stops.
        }

        // print_otp(int otp) => OTP is the first integer argument to print_otp.
        int otp = (int)(regs.rdi & 0xffffffffU); // Extracting the OTP from the registers.
        printf("OTP: %06d\n", otp);
        send_otp(otp); // Sending the OTP to the client.

        // Rewind RIP, restore instruction, single-step once, then reinsert breakpoint.
        regs.rip = bp_addr;
        if (ptrace(PTRACE_SETREGS, pid, NULL, &regs) < 0) {
            perror("PTRACE_SETREGS"); // Failed to set the registers.
            return 1;
        }

        if (ptrace(PTRACE_POKETEXT, pid, (void *)bp_addr, (void *)original) < 0) {
            perror("PTRACE_POKETEXT restore"); // Failed to restore the original instruction.
            return 1;
        }
        if (ptrace(PTRACE_SINGLESTEP, pid, NULL, NULL) < 0) {
            perror("PTRACE_SINGLESTEP"); // Failed to single-step the child process.
            return 1;
        }
        if (!wait_for_stop(pid)) return 1;

        if (ptrace(PTRACE_POKETEXT, pid, (void *)bp_addr, (void *)with_breakpoint) < 0) {
            perror("PTRACE_POKETEXT reinsert"); // Failed to reinsert the breakpoint.
            return 1;
        }
    }
}
