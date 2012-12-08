<?php
/**
 * SMTP
 *
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     SMTP
 * @author      Sergio Vaccaro <sergiovaccaro67@gmail.com>
 * @copyright   Copyright (c) Sergio Vaccaro
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt     GPLv3
 * @version     1.1
 */

/**
 * Simple SMTP client
 *
 * @package     SMTP
 * @link        http://en.wikipedia.org/wiki/Simple_Mail_Transfer_Protocol Documentation
 */
class smtp
{
    /**
     * New line character
     *
     * SMTP wants <CR><LF>.<CR><LF>
     */
    const NL = "\r\n";

    /**
     * Ready status code
     * @link http://www.greenend.org.uk/rjk/tech/smtpreplies.html
     */
    const READY = '220';

    /**
     * Ok status code
     * @link http://www.greenend.org.uk/rjk/tech/smtpreplies.html
     */
    const OK = '250';

    /**
     * Text encoded as base64
     * @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const TEXT64 = '334';

    /**
     * Auth OK
     * @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const AUTHOK = '235';

    /**
     * Data ok
     * @link @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const DATAOK = '354';

    /**
     * Bye
     * @link @link http://www.fehcom.de/qmail/smtpauth.html
     */
    const BYE = '221';

    /**
     * Mailer
     */
    const MAILER = 'PHP smtp class';

    /**
     * Mailer author
     */
    const MAILER_AUTHOR = '"Sergio Vaccaro" <hujuice@inservibile.org> https://github.com/hujuice/smtp';

    /**
     * SMTP socket resource
     *
     * @var resource
     */
    protected $_smtp;

    /**
     * Auth user (base64 encoded)
     *
     * @var string
     */
    protected $_user;

    /**
     * Auth pass (base64 encoded)
     *
     * @var string
     */
    protected $_pass;

    /**
     * From
     *
     * @var string
     */
    protected $_from;

    /**
     * From (name)
     *
     * @var string
     */
    protected $_fromName;

    /**
     * To
     *
     * Multiple recipients allowed
     *
     * @var array
     */
    protected $_to = array();

    /**
     * Cc
     *
     * Multiple recipients allowed
     *
     * @var array
     */
    protected $_cc = array();

    /**
     * Bcc
     *
     * Multiple recipients allowed
     *
     * @var array
     */
    protected $_bcc = array();

    /**
     * Content-Type
     *
     * @var string
     */
    protected $_contentType = 'text/plain';

    /**
     * Charset
     *
     * @var string
     */
    protected $_charset = 'utf-8';

    /**
     * Subject
     *
     * @var string
     */
    protected $_subject;

    /**
     * Message
     *
     * @var string
     */
    protected $_body;

    /**
     * Log
     *
     * @var string
     */
    protected $_log = '';

    /**
     * Add or replace recipients
     *
     * @param string $dest
     * @param string $destName
     * @param array $class
     * @return void
     * @throw Exception
     */
    protected function _recipients($dest, $destName, $class)
    {
        if (in_array($class, array('_to', '_cc', '_bcc')))
        {
            if ($destName)
            {
                if ($dest)
                    $this->{$class}[$destName] = $dest;
                else
                {
                    if (isset($this->{$class}[$destName]))
                        unset($this->{$class}[$destName]);
                }
            }
            else
            {
                if ($dest)
                    $this->{$class}[] = $dest;
            }
        }
        else
            throw new Exception('Wrong recipient');
    }

    /**
     * Perform a request/response exchange
     *
     * @param string $request
     * @param string $expect The expected status code
     * @return string
     * @throw Exception
     */
    protected function _dialog($request, $expect)
    {
        $this->_log .= $request . PHP_EOL;

        fwrite ($this->_smtp, $request . self::NL);
        $response = fgets($this->_smtp);

        $this->_log .= $response . PHP_EOL;

        if (substr($response, 0, 3) != $expect)
            throw new Exception('Message "' . $request . '" NOT accepted! Here is the dialog dump:' . PHP_EOL . $this->_log);

        return $response;
    }

    /**
     * Connection to the SMTP server
     *
     * @param string $host
     * @param integer $port
     * @param integer $timeout
     * @return void
     * @throw exception
     */
    public function __construct($host, $port = 25 , $timeout = 3)
    {
        // Avoid a warning
        if (empty($host))
            throw new Exception('Undefined SMTP server');

        // Connect
        if ($this->_smtp = fsockopen($host, $port, $errno, $errstr, $timeout))
        {
            if (substr($response = fgets($this->_smtp), 0, 3) != self::READY)
                throw new Exception('Server NOT ready! The server responded with this message:' . PHP_EOL . $response);

            $this->_log = $response . PHP_EOL;
        }
        else
        {
            $message = 'Unable to connect to ' . $host . ' on port ' . $port . ' within ' . $timeout . ' seconds' . PHP_EOL;
            if (!empty($errstr))
                $message .= 'The remote server responded:' . PHP_EOL . $errstr . '(' . $errno . ')';
            throw new Exception($message);
        }
    }

    /**
     * Closes connection
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->_smtp)
            fclose($this->_smtp);
    }

    /**
     * Auth
     *
     * Auth login implementation.
     * Consider that there are many auth types.
     *
     * @param string $user
     * @param string $pass
     * @return void
     */
    public function auth($user, $pass)
    {
        $this->_user = base64_encode($user);
        $this->_pass = base64_encode($password);
    }

    /**
     * From
     *
     * @param string $from
     * @param string $name
     * @return array
     */
    public function from($from = null, $name = '')
    {
        if ($from)
        {
            $this->_from = (string) $from;
            $this->_fromName = (string) $name;
        }
        return array('from' => $this->_from, 'name' => $this->_fromName);
    }

    /**
     * To
     *
     * @param string $to
     * @param string $toName
     * @return array
     */
    public function to($to = null, $toName = '')
    {
        $this->_recipients($to, $toName, '_to');
        return $this->_to;
    }

    /**
     * Cc
     *
     * @param string $cc
     * @param string $ccName
     * @return array
     */
    public function cc($cc = null, $ccName = '')
    {
        $this->_recipients($cc, $ccName, '_cc');
        return $this->_cc;
    }

    /**
     * Bcc
     *
     * @param string $bcc
     * @param string $bccName
     * @return array
     */
    public function bcc($bcc = null, $bccName = '')
    {
        $this->_recipients($bcc, $bccName, '_bcc');
        return $this->_bcc;
    }

    /**
     * Content Type
     *
     * @link http://en.wikipedia.org/wiki/MIME#Multipart_messages
     * @param string $content_type
     * @return string;
     */
    public function contentType($content_type = null)
    {
        if (null !== $content_type)
            $this->_contentType = (string) $content_type;
        return $this->_contentType;
    }

    /**
     * Charset
     *
     * @param string $charset
     * @return string
     */
    public function charset($charset = null)
    {
        if (null !== $charset)
            $this->_charset = (string) $charset;
        return $this->_charset;
    }

    /**
     * Subject
     *
     * @param string $subject
     * @return string
     */
    public function subject($subject = null)
    {
        if (null !== $subject)
            $this->_subject = (string) $subject;
        return $this->_subject;
    }

    /**
     * Body
     *
     * @param string $body
     * @param boolean $append
     * @return string
     */
    public function body($body = null, $append = false)
    {
        if (null !== $body)
        {
            $body = str_replace("\n", self::NL, (string) $body);

            if ($append)
                $this->_body .= $body;
            else
                $this->_body = $body;
        }
        return $this->_body;
    }

    /**
     * Send
     *
     * @return string
     * @throw Exception
     */
    public function send()
    {
        // Check for minimum requirements
        if (empty($this->_from))
            throw new Exception('Sender undefined');

        if (empty($this->_to) && empty($this->_cc) && empty($this->_bcc))
            throw new Exception('No recipients');

        if (empty($this->_subject)) // Net Ecology
            throw new Exception('No subject');

        if (empty($this->_body))
            throw new Exception('No message body');

        // HELO
        $sender = explode('@', $this->_from);
        $this->_dialog('HELO ' . $sender[1], self::OK);

        // Auth
        if ($this->_user && $this->_pass)
        {
            // See http://www.fehcom.de/qmail/smtpauth.html
            $this->_dialog('auth login', self::TEXT64);
            $this->_dialog($this->_user, self::TEXT64);
            $this->_dialog($this->_pass, self::AUTHOK);
        }

        // From
        $this->_dialog('MAIL FROM:<' . $this->_from . '>', self::OK);

        // Recipients
        foreach($this->_to as $rcpt)
            $this->_dialog('RCPT TO:<' . $rcpt . '>', self::OK);
        foreach($this->_cc as $rcpt)
            $this->_dialog('RCPT TO:<' . $rcpt . '>', self::OK);
        foreach($this->_bcc as $rcpt)
            $this->_dialog('RCPT TO:<' . $rcpt . '>', self::OK);

        // Data
        $this->_dialog('DATA', self::DATAOK);

        // Message
        $message = '';

        // From
        if (empty($this->_fromName))
            $message .= 'From: <' . $this->_from . '>' . self::NL;
        else
            $message .= 'From: "' . $this->_fromName . '"<' . $this->_from . '>' . self::NL;

        // To
        foreach ($this->_to as $name => $rcpt)
        {
            if (is_integer($name))
                $message .= 'To: <' . $rcpt . '>' . self::NL;
            else
                $message .= 'To: "' . $name . '"<' . $rcpt . '>' . self::NL;
        }

        // Cc
        foreach ($this->_cc as $name => $rcpt)
        {
            if (is_integer($name))
                $message .= 'Cc: <' . $rcpt . '>' . self::NL;
            else
                $message .= 'Cc: "' . $name . '"<' . $rcpt . '>' . self::NL;
        }

        // Bcc
        foreach ($this->_bcc as $name => $rcpt)
        {
            if (is_integer($name))
                $message .= 'Bcc: <' . $rcpt . '>' . self::NL;
            else
                $message .= 'Bcc: "' . $name . '"<' . $rcpt . '>' . self::NL;
        }

        // Date
        $message .= 'Date: ' . date('r') . self::NL;

        // Subject
        $message .= 'Subject: ' . $this->_subject . self::NL;

        // Mailer
        $message .= 'X-mailer: ' . self::MAILER . self::NL;
        $message .= 'X-mailer-author: ' . self::MAILER_AUTHOR . self::NL;

        // Content-Type
        $message .= 'Content-Type: ' . $this->_contentType . '; charset=' . $this->_charset . self::NL;

        // Message
        $message .= self::NL . $this->_body . self::NL . '.';

        // Body!
        $send = $this->_dialog($message, self::OK);

        // Quit
        $this->_dialog('QUIT', self::BYE);
        return substr($send, 4);
    }

    /**
     * Dump the log
     *
     * @return string
     */
    public function dump()
    {
        return $this->_log;
    }
}