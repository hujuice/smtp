SMTP
====
Rich SMTP client

Basic usage
-----------
    $smtp = new Smtp('smtp_server');
    $smtp->from('user@domain');
    $smtp->to('dest@anotherdomain');
    $smtp->subject('subject');
    $smtp->text('message');
    $smtp->send();

Advanced usage
--------------
    $smtp = new Smtp('smtp_server', 25, 3);
    $smtp->from('user@domain', 'My name');
    $smtp->mailFrom('my_account@domain');
    $smtp->replyTo('anotheraddress@anotherdomain');
    $smtp->priority(4);
    $smtp->header('MyCustomHeader', 'The value of my custom header');
    $smtp->to('addr1@domain', 'My friend');
    $smtp->to('addr2@anotherdomain', 'Another friend');
    $smtp->cc('addr3@domain', 'My mama');
    $smtp->cc('addr4@domain', 'My papa');
    $smtp->bcc('addr5@domain', 'My cat');
    $smtp->subject('Birthday invitation');
    $smtp->text('Want you come to my party?', 'text/plain', 'utf-8');
    $smtp->attachment('/path/to/my/picture', 'me.jpg', 'image/jpeg');
    $smtp->attachment('/path/to/businesscard', 'me.html', 'text/html', 'utf-8');
    $smtp->raw('Here are directions to my home', 'directions.txt', 'text/plain', 'iso-8859-1');
    $imagedata = imagecreatefrompng($imagefile);
    // Add watermarks
    ob_start();
    imagepng($imagedata);
    $stringdata = ob_get_contents(); // read from buffer
    ob_end_clean();
    $smtp->raw($stringdata, 'Flyer.jpg', 'image/jpeg');
    $smtp->charset('UTF-8'); // For subject, names, etc.
    $smtp->send();

    $smtp->clear(); // Clear recipients and attachments
    $smtp->from('anewsender@anewdomain');
    $smtp->mailFrom('setanewmailfrom@anewdomain');
    $smtp->replyTo('');
    $smtp->to('anewdest@anothernewdomain');
    $smtp->subject('newsubject');
    $smtp->text('newmessage');
    $smtp->send();

    echo $smtp->dump(); // Dump the full logging

FEATURES
--------
- multiple recipients
- To, Cc, Bcc recipients
- named recipients
- separate 'MAIL FROM' management
- Reply-To management
- priority management
- custom headers
- Content-Type equipped text (e.g. text/html)
- attachments from file
- attachments from string
- binary attachments (from file or string)
- Content-Type full management, per part
- 'auth login' authentication (see http://www.fehcom.de/qmail/smtpauth.html)