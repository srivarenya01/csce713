#define _GNU_SOURCE
#include <stdio.h>
#include <dlfcn.h>
#include <sys/types.h>
#include <sys/ptrace.h>
#include <stdarg.h>
#include <unistd.h>

long ptrace(enum __ptrace_request request, ...) {
    static long (*real_ptrace)(enum __ptrace_request, ...) = NULL;
    
    if (!real_ptrace) {
        real_ptrace = dlsym(RTLD_NEXT, "ptrace");
    }

    if (request == PTRACE_TRACEME) {
        return 0;
    }

    va_list ap;
    va_start(ap, request);
    pid_t pid = va_arg(ap, pid_t);
    void *addr = va_arg(ap, void *);
    void *data = va_arg(ap, void *);
    va_end(ap);
    
    return real_ptrace(request, pid, addr, data);
}
