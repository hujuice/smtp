smtp
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

FEATURES
--------
- multiple recipients
- To, Cc, Bcc recipients
- named recipients
- Content-Type equipped text (e.g. text/html)
- attachments from file
- attachments from string
- binary attachments (from file or string)
- Content-Type full management, per part
- 'auth login' authentication (see http://www.fehcom.de/qmail/smtpauth.html)