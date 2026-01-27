<?php
// Flag obfuscation helper - DO NOT SHARE WITH STUDENTS
// This file contains the decoding logic for obfuscated flags
// Flags are stored as: Base64(Reverse(ROT13(original)))

function decode_flag($obfuscated) {
    // Step 1: Base64 decode
    $decoded = base64_decode($obfuscated);
    if ($decoded === false) {
        return $obfuscated; // Return as-is if decode fails
    }
    
    // Step 2: Reverse string
    $unreversed = strrev($decoded);
    
    // Step 3: ROT13 decode
    $original = str_rot13($unreversed);
    
    return $original;
}

// Function to encode flag (for testing/setup)
function encode_flag($flag) {
    // Step 1: ROT13
    $rot13 = str_rot13($flag);
    // Step 2: Reverse
    $reversed = strrev($rot13);
    // Step 3: Base64
    $encoded = base64_encode($reversed);
    return $encoded;
}

// Load and decode flag from filesystem
function load_file_flag() {
    // Hidden flag location
    $flag_file = '/var/www/.system/.cache_data';
    
    if (file_exists($flag_file)) {
        $obfuscated = trim(file_get_contents($flag_file));
        return decode_flag($obfuscated);
    }
    
    return null;
}
?>
