<?php
$lines = explode("\n", file_get_contents(__DIR__ . '/../public/swagger.json'));

for ($i = 1; $i <= count($lines); $i++) {
    $slice = implode("\n", array_slice($lines, 0, $i));
    // Check if syntax error happens at line $i
    // We try to close open braces to see if valid
    $openBraces = substr_count($slice, '{') - substr_count($slice, '}');
    $openBrackets = substr_count($slice, '[') - substr_count($slice, ']');
    $testJson = $slice . str_repeat('}', max(0, $openBraces)) . str_repeat(']', max(0, $openBrackets));
    
    // Check for trailing commas
    $cleaned = preg_replace('/,\s*([}\]])/', '$1', $testJson);
    json_decode($cleaned);
    if (json_last_error() === JSON_ERROR_SYNTAX) {
        echo "Syntax error first appears around line {$i}: " . trim($lines[$i-1]) . "\n";
        break;
    }
}
