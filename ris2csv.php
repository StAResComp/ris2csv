<?php

// map RIS fields to CSV fields
// keys longer than 2 letters are not RIS fields
$map = array('A1' => 'Author', 'primary_author' => 'Primary author', 'presentation_authors' => 'Presentation authors', 'author_count' => 'Author count',
             'T1' => 'Title', 'JF' => 'Journal', 
             'Y1' => 'Year', 'DO' => 'DOI', 'N2' => 'Custom1', 'UR' => 'URL');

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

// convert author names like Bloggs, Joe P into J. P. Bloggs
function normaliseNames($names) { //{{{
    $normalised = array();
    $maxNames = 3;
    
    $n = 0;
    foreach ($names as $i => $name) {
        // skip names after third
        if (++ $n > $maxNames) {
            continue;
        }
        
        // split name into surname and forenames and split up forenames
        list ($surname, $forenames) = explode(', ', $name, 2);
        $forenames = explode(' ', $forenames);
        $fullname = '';
        
        // check that first letter of forename is uppercase
        foreach ($forenames as $fn) {
            $i = substr($fn, 0, 1);
            // add to full name as initial
            if (IntlChar::isupper($i)) {
                $fullname .= sprintf('%s. ', $i);
            }
        }
        
        // add surname and add to normalised
        $fullname .= $surname;
        $normalised[] = $fullname;
    }
    
    // join names
    $presentation = implode(', ', $normalised);
    
    // check whether to add et al
    if ($n > $maxNames) {
        $presentation .= ' et al';
    }
    
    return $presentation;
}
//}}}

function normaliseJournal($journal) { //{{{
    // words to remain lc
    $lcWords = array('in', 'of', 'for', 'the', 'and');
    
    $words = explode(' ', $journal);
    foreach ($words as $i => $w) {
        // change & to and
        if ('&' == $w) {
            $words[$i] = 'and';
        }
        // word not in lc words
        elseif (!in_array($w, $lcWords)) {
            $words[$i] = substr_replace($w, IntlChar::toupper(substr($w, 0, 1)), 
                                        0, 1);
        }
    }
    
    // return words joined by space
    return implode(' ', $words);
}
//}}}

// convert record into line for CSV file
function writeRecord($record) { //{{{
    global $map;
    
    $arr = array();
    
    // loop over fields in record
    foreach (array_keys($map) as $tag) {
        $value = $record[$tag];
        
        // handle empty RIS values here
        if (2 == strlen($tag) && !$value) {
            $arr[$tag] = '';
            continue;
        }
        
        switch ($tag) {
            // semi colon delimit authors
            // and create primary author and presentation author fields
         case 'A1':
            $arr[$tag] = implode('; ', $value);
            $arr['primary_author'] = $value[0];
            $arr['presentation_authors'] = normaliseNames($value);
            $arr['author_count'] = count($value);
            break;
            
            // should only be 1 title/abstract
         case 'T1':
         case 'N2':
         case 'DO':
            $arr[$tag] = $value[0];
            break;
            
            // normalise capitalisation of journal names
         case 'JF':
            $arr[$tag] = normaliseJournal($value[0]);
            break;
            
            // year - want first 4 digits
         case 'Y1':
            $arr[$tag] = substr($value[0], 0, 4);
            break;
            
            // URL - leave as array
         case 'UR':
            foreach ($value as $i => $url) {
                $arr[sprintf('%s%d', $tag, $i)] = $url;
            }
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