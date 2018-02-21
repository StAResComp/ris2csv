<?php

require_once 'ris2csv.php';

$message = '';

do {
    // form fields
    $field = 'file';
    
    // file uploaded
    if (isset($_FILES[$field])) {
        // check for upload error
        if (UPLOAD_ERR_OK != $_FILES[$field]['error']) {
            $message = 'There was an upload error';
            break;
        }
        
        // check that temporary location is genuine uploaded file
        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $message = 'Problem with uploaded file';
            break;
        }
        
        // file has to end in .ris
        if (!preg_match('/^.*\.ris$/', $_FILES[$field]['name'])) {
            $message = 'File must end .ris';
            break;
        }
        
        // try to open uploaded file
        $ris = fopen($_FILES[$field]['tmp_name'], 'r');
        if (!$ris) {
            $message = 'RIS file couldn\'t be opened';
            break;
        }
        
        // try to open temp CSV file
        $csv = tmpfile();
        if (!$csv) {
            $message = 'CSV file couldn\'t be opened';
            break;
        }
        
        // run RIS to CSV function
        if (!ris2csv($ris, $csv, $message)) {
            break;
        }
        
        // headers for downloading CSV
        header('Content-type: text/csv');
        header(sprintf('Content-disposition: attachment; filename=%s',
                       preg_replace('/^(.*)\.ris$/', '${1}.csv', 
                                    $_FILES[$field]['name'])));
        
        // output CSV
        fseek($csv, 0);
        while ($data = fread($csv, 1024)) {
            print $data;
        }
        
        // close files
        fclose($ris);
        fclose($csv);
        
        break;
    }
    
    // output form
    $message = <<<EOT
      <html>
      <head>
      <title>RIS upload</title>
      </head>
      <body>
      <h1>RIS upload</h1>
      <form enctype="multipart/form-data" method="post" action="index.php">
      <p>
      <label for="${field}">Select file</label>
      <input type="file" name="${field}" id="${field}"/>
      </p>
      <p>
      <input type="submit" value="Upload"/>
      </p>
      </form>
      </body>
      </html>
EOT;
} while (false);

print $message;


?>