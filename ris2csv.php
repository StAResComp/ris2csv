<?php

// map RIS fields to CSV fields
$map = array('A1' => 'Author', 'T1' => 'Title', 'JF' => 'Journal', 
             'Y1' => 'Year', 'UR' => 'URL', 'N2' => 'Custom1');

// initialise record as hash of arrays
function initialiseRecord() { //{{{
    global $map;
    
    $record = array();
    
    foreach (array_keys($map) as $tag) {
        $record[$tag] = array();
    }
    
    return $record;
}
//}}}

// convert record into line for CSV file
function writeRecord($record) { //{{{
    global $map;
    
    $arr = array();
    
    // loop over fields in record
    foreach (array_keys($map) as $tag) {
        $value = $record[$tag];
        
        // handle empty values here
        if (!$value) {
            $arr[$tag] = '';
            continue;
        }
        
        switch ($tag) {
            // comma delimit authors
         case 'A1':
            $arr[$tag] = implode(', ', $value);
            break;
            
            // should only be 1 title/journal/abstract
         case 'T1':
         case 'JF':
         case 'N2':
            $arr[$tag] = $value[0];
            break;
            
            // year - want first 4 digits
         case 'Y1':
            $arr[$tag] = substr($value[0], 0, 4);
            break;
            
            // URL - space delimited
         case 'UR':
            $arr[$tag] = implode(' ', $value);
            break;
        }
    }
    
    return $arr;
}
//}}}

// read in data from RIS file handle and output to CSV file handle
function ris2csv($ris, $csv, &$message) { //{{{
    global $map;
    
    $success = false;
    
    do {
        // CSV header
        if (!fputcsv($csv, array_values($map))) {
            $message = 'couldn\'t write header to CSV';
            break;
        }
        
        $record = initialiseRecord();
        
        // loop over lines in RIS
        while (false !== ($line = fgets($ris))) {
            list ($tag, $value) = explode('  - ', $line, 2);
            $value = trim($value);
            
            // at end of record
            if ('ER' == $tag) {
                // write record to CSV
                if (!fputcsv($csv, writeRecord($record))) {
                    $message = 'couldn\'t write record to CSV';
                    break 2;
                }
                
                // reset record and continue
                $record = initialiseRecord();
                continue;
            }
            
            // not interested in this tag
            if (!isset($map[$tag])) {
                continue;
            }
            
            // add this value to array for tag in record
            $record[$tag][] = $value;
        }
        
        $success = true;
    } while (false);
    
    return $success;
}
//}}}

?>