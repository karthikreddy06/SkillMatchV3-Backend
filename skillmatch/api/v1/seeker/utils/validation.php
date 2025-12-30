<?php

function validate_resume_file($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return "Invalid resume file";
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB
        return "Resume size exceeds 5MB";
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== "pdf") {
        return "Only PDF resume allowed";
    }

    return true;
}

function validate_string($value, $min = 1, $max = 255) {
    $value = trim($value ?? "");
    if (strlen($value) < $min) return "String too short";
    if (strlen($value) > $max) return "String too long";
    return true;
}

function validate_numeric_range($value, $min, $max) {
    if (!is_numeric($value)) return "Invalid number";
    if ($value < $min || $value > $max) return "Number out of range";
    return true;
}
