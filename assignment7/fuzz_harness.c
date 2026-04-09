#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <unistd.h>

int check_flag(char *input) {
    unsigned char enc2[] = {
        0x19, 0xe3, 0xd7, 0xbe, 0x84, 0x91, 0x39, 
        0x56, 0x04, 0x3c, 0x04, 0x1b, 0xf6, 0x8b
    };
    int len = 14;
    unsigned char key = 127;
    char target[15];
    
    // XOR LOGIC
    for (int i = 0; i < len; i++) {
        target[i] = enc2[i] ^ key;
        key += 23;
    }


    for (int i = 0; i < len; i++) {
        if (input[i] != target[i]) {
            return 0;
        }
    }
    return 1; 
}

int main(int argc, char *argv[]) {
    char buf[256];
    
    if (fgets(buf, sizeof(buf), stdin) == NULL) return 0;
    
    buf[strcspn(buf, "\n")] = 0;

    if (check_flag(buf)) {
        printf("Correct input found!\n");
        abort(); 
    }
    
    return 0;
}
