rule initial_rule {
    meta:
        description = "Detects the rootkit for Assignment 5"
        author = "varenya sri mudumba"
    strings:
        $s1 = "/home/student/.secret"
    condition:
        $s1
}

rule enhanced_rule {
    meta:
        description = "Detects the enhanced rootkit"
        author = "varenya sri mudumba"
    strings:
        $f1 = "readdir"
        $f2 = "open"
        $f3 = "open64"
        $f4 = "openat"
        $f5 = "__xstat"
        $d1 = "dlsym"
    condition:
        $d1 and ($f1 or $f2 or $f3 or $f4 or $f5)
}