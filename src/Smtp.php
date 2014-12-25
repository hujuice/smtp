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
 * @version     1.3
 */

namespace Hujuice\Smtp;
use Exception;

/**
 * Rich SMTP client
 *
 * @package     SMTP
 * @link        http://en.wikipedia.org/wiki/Simple_Mail_Transfer_Protocol Documentation
 */
class Smtp
{
    /**
     * New line character
     *
     * SMTP wants <CR><LF>
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
    protected $_smtp = null;

    /**
     * Server
     *
     * @var array
     */
    protected $_server = array(
        'host'      => null,
        'port'      => 25,
        'timeout'   => 3,
    );

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
     * Charset (excluding text and attachments)
     *
     * @var string
     */
    protected $_charset = 'UTF-8';

    /**
     * From
     *
     * @var array
     */
    protected $_from;

    /**
     * MAIL FROM
     *
     * @var string
     */
    protected $_mailFrom = null;

    /**
     * Reply-to
     *
     * @var array
     */
    protected $_replyTo = array();

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
     * Priority
     *
     * Priorities are from 1 (low) to 5 (high)
     * 3 is normal
     *
     * @var integer
     */
    protected $_priority;

    /**
     * Custom headers
     *
     * @var array
     */
    protected $_headers = array();

    /**
     * Subject
     *
     * @var string
     */
    protected $_subject;

    /**
     * Text message
     *
     * @var string
     */
    protected $_text = array(
        'body'          => '',
        'Content-Type'  => 'text/plain',
        'charset'       => 'UTF-8'
    );

    protected $_body = null;

    /**
     * File attachments
     *
     * @var array
     */
    protected $_attachments = array();

    /**
     * Raw attachments
     *
     * @var array
     */
    protected $_raw = array();

    /**
     * Log
     *
     * @var string
     */
    protected $_log = '';

    protected $_pipelining = true;

    protected $_pipelinedCommands = array();

    /**
     * Charset encoding
     *
     * @see http://www.pcvr.nl/tcpip/smtp_sim.htm
     * @param string $string
     * @return string
     */
    protected function _encode($string)
    {
        if ($this->_charset) {
            return '=?' . $this->_charset . '?B?' . base64_encode($string) . '?=';
        } else {
            return $string;
        }
    }

    /**
     * Add or replace recipients
     *
     * @param string $dest
     * @param string $destName
     * @param array $class
     * @throws Exception
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

    protected function _readResponse($expected)
    {
        $response = '';
        while (($line = fgets($this->_smtp)) !== false) {
            $response .= $line;
            if ($line[3] != '-') {
                break;
            }
        }
        $this->_log .= $response;

        if (substr($response, 0, 3) != $expected) {
            throw new Exception("Unexpected response. Expected {$expected}. Here is the dialog dump:\n{$this->_log}");
        }

        return $response;
    }

    /**
     * Perform a request/response exchange
     *
     * @param string $request
     * @param string $expect The expected status code
     * @return string
     * @throws Exception
     */
    protected function _dialog($request, $expect)
    {
        $this->_log .= $request . PHP_EOL;

        fwrite($this->_smtp, $request . self::NL);

        if ($this->_pipelining) {
            // is pipelinable command?
            if (in_array(substr($request, 0, 4), array('RSET', 'MAIL', 'SEND', 'SOML', 'SAML', 'RCPT'))) {
                $this->_pipelinedCommands[] = $expect;
                return null;
            } else {
                while ($this->_pipelinedCommands) {
                    $_expected = array_shift($this->_pipelinedCommands);
                    $this->_readResponse($_expected);
                }
            }
        }

        return $this->_readResponse($expect);
    }

    /**
     * Connection to the SMTP server
     * @throws Exception
     */
    public function _connect()
    {
        // Connect (if not already connected)
        if (empty($this->_smtp))
        {
            if ($this->_smtp = fsockopen($this->_server['host'], $this->_server['port'], $errno, $errstr, $this->_server['timeout']))
            {
                if (substr($response = fgets($this->_smtp), 0, 3) != self::READY)
                    throw new Exception('Server NOT ready! The server responded with this message:' . PHP_EOL . $response);

                $this->_log = $response . PHP_EOL;

                // HELO
                $sender = explode('@', $this->_from['address']);
                $ehlo = $this->_dialog('EHLO ' . $sender[1], self::OK);
                $this->_pipelining = preg_match('~250[\s-]pipelining~i', $ehlo);

                // Auth
                if ($this->_user && $this->_pass)
                {
                    // See http://www.fehcom.de/qmail/smtpauth.html
                    $this->_dialog('auth login', self::TEXT64);
                    $this->_dialog($this->_user, self::TEXT64);
                    $this->_dialog($this->_pass, self::AUTHOK);
                }
            }
            else
            {
                $message = 'Unable to connect to ' . $this->_server['host'] . ' on port ' . $this->_server['port'] . ' within ' . $this->_server['timeout'] . ' seconds' . PHP_EOL;
                if (!empty($errstr))
                    $message .= 'The remote server responded:' . PHP_EOL . $errstr . '(' . $errno . ')';
                throw new Exception($message);
            }
        }
    }

    /**
     * Connection to the SMTP server
     *
     * @param string $host
     * @param integer $port
     * @param integer $timeout
     * @throws Exception
     */
    public function __construct($host, $port = 25 , $timeout = 3)
    {
        // Avoid a warning
        if (empty($host))
            throw new Exception('Undefined SMTP server');

        // Settings
        $this->_server['host'] = (string) $host;
        if ($port)
            $this->_server['port'] = (integer) $port;
        if ($timeout)
            $this->_server['timeout'] = (integer) $timeout;
    }

    /**
     * Closes connection
     */
    public function __destruct()
    {

        if ($this->_smtp) {
            // Quit
            $this->_dialog('QUIT', self::BYE);
            fclose($this->_smtp);
        }
    }

    /**
     * Auth
     *
     * Auth login implementation.
     * Consider that there are many auth types.
     *
     * @param string $user
     * @param string $pass
     */
    public function auth($user, $pass)
    {
        $this->_user = base64_encode($user);
        $this->_pass = base64_encode($pass);
    }

    /**
     * Charset (excluding text and attachments)
     * Note that this is the charset for Subject, names, etc.
     * In a web context, should match the 'Content-Type'.
     * If empty will be pure ASCII (7-bit)
     *
     * @param string
     */
    public function charset($charset)
    {
        $this->_charset = (string) $charset;
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
        if (null !== $from)
        {
            $this->_from['address'] = (string) $from;
            $this->_from['name'] = (string) $name;
        }
        return $this->_from;
    }

    /**
     * MAIL FROM
     *
     * @param string $mail_from
     * @return string
     */
    public function mailFrom($mail_from = null)
    {
        if (null !== $mail_from)
            $this->_mailFrom = (string) $mail_from;

        return $this->_mailFrom;
    }

    /**
     * Reply-to
     *
     * @param string $reply_to
     * @param string $name
     * @return array
     */
    public function replyTo($reply_to = null, $name = null)
    {

        if (null !== $reply_to)
        {
            $this->_replyTo['address'] = (string) $reply_to;
            $this->_replyTo['name'] = (string) $name;
        }
        return $this->_replyTo;
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
     * Priority
     *
     * @param integer $priority
     * @throws Exception
     * @return integer
     */
    public function priority($priority = null)
    {
        if ($priority)
        {
            $priority = (integer) $priority;
            if (($priority > 0) && ($priority < 6))
                $this->_priority = $priority;
            else
                throw new Exception('Priority are integer from 1 (low) to 5 (high)');
        }
        return $this->_priority;
    }

    /**
     * Custom header
     *
     * @param string $name
     * @param string $value
     * @return array
     */
    public function header($name = null, $value = null)
    {
        if ($name)
            $this->_headers[(string) $name] = (string) $value;
        return $this->_headers;
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
     * Text
     *
     * @param string $text
     * @param string $content_type
     * @param string $charset
     * @return string
     */
    public function text($text = null, $content_type = 'text/plain', $charset = 'utf-8')
    {
        if (null !== $text)
        {
            $this->_text = array(
                'body'          => str_replace("\n", self::NL, (string) $text),
                'Content-Type'  => $content_type,
                'charset'       => $charset
            );
        }
        return $this->_text;
    }

    public function body($body)
    {
        $this->_body = $body;
    }

    /**
     * Attachment from file
     *
     * @link http://en.wikipedia.org/wiki/MIME#Content-Transfer-Encoding
     * @link http://en.wikipedia.org/wiki/MIME#Multipart_messages
     * @link http://support.mozilla.org/it/questions/746116
     * @param string $path
     * @param string $name
     * @param string $content_type
     * @param string $charset Will be used for text/* only
     * @throws Exception
     * @return array
     */
    public function attachment($path = null, $name = '', $content_type = 'application/octet-stream', $charset = 'utf-8')
    {
        if (is_readable($path))
        {
            $attachment = array(
                'path'          => (string) $path,
                'Content-Type'  => (string) $content_type,
                'charset'       => (string) $charset
            );

            $name || ($name = pathinfo($path, PATHINFO_BASENAME));

            $this->_attachments[$name] = $attachment;
        }
        elseif(!empty($path))
            throw new Exception('File ' . $path . ' not found or not readable');

        return $this->_attachments;
    }

    /**
     * Raw attachment
     *
     * @link http://en.wikipedia.org/wiki/MIME#Content-Transfer-Encoding
     * @link http://en.wikipedia.org/wiki/MIME#Multipart_messages
     * @link http://support.mozilla.org/it/questions/746116
     * @param string $name
     * @param string $content
     * @param string $content_type
     * @param string $charset Will be used for text/* only
     * @return array
     */
    public function raw($content = null, $name = '', $content_type = 'text/plain', $charset = 'utf-8')
    {
        if ($content)
        {
            $attachment = array(
                'content'       => (string) $content,
                'Content-Type'  => (string) $content_type,
                'charset'       => (string) $charset
            );

            if (empty($name))
                $name = time() . '-' . mt_rand();
            $this->_raw[$name] = $attachment;
        }

        return $this->_raw;
    }

    /**
     * Completely clear recipients, attachments and headers (for a new message)
     */
    public function clear()
    {
        $this->_to = array();
        $this->_cc = array();
        $this->_bcc = array();
        $this->_headers = array();
        $this->_attachments = array();
        $this->_raw = array();
        $this->_body = null;
    }

    /**
     * Send
     *
     * @see http://www.pcvr.nl/tcpip/smtp_sim.htm
     * @return string
     * @throws Exception
     */
    public function send()
    {
        // Check for minimum requirements
        if (empty($this->_from))
            throw new Exception('Sender undefined');

        if (empty($this->_to) && empty($this->_cc) && empty($this->_bcc))
            throw new Exception('No recipients');

        if (empty($this->_body)) {
            if (empty($this->_subject)) // Net Ecology
                throw new Exception('No subject');

            if (empty($this->_text))
                throw new Exception('No message text');
        }

        // Connection
        $this->_connect();

        // From
        if ($this->_mailFrom)
            $from = $this->_mailFrom;
        else
            $from = $this->_from['address'];
        $this->_dialog('MAIL FROM:<' . $from . '>', self::OK);

        // Recipients
        foreach($this->_to as $rcpt)
            $this->_dialog('RCPT TO:<' . $rcpt . '>', self::OK);
        foreach($this->_cc as $rcpt)
            $this->_dialog('RCPT TO:<' . $rcpt . '>', self::OK);
        foreach($this->_bcc as $rcpt)
            $this->_dialog('RCPT TO:<' . $rcpt . '>', self::OK);

        // Data
        $this->_dialog('DATA', self::DATAOK);

        if ($this->_body) {
            $message = $this->_body . self::NL;
        } else {
            // Message
            $message = '';

            // From
            if (empty($this->_from['name']))
                $message .= 'From: <' . $this->_from['address'] . '>' . self::NL;
            else
                $message .= 'From: "' . $this->_encode($this->_from['name']) . '"<' . $this->_from['address'] . '>' . self::NL;

            // Reply to
            if (!empty($this->_replyTo))
            {
                if (empty($this->_replyTo['name']))
                    $message .= 'Reply-To: <' . $this->_replyTo['address'] . '>' . self::NL;
                else
                    $message .= 'Reply-To: "' . $this->_encode($this->_replyTo['name']) . '"<' . $this->_replyTo['address'] . '>' . self::NL;
            }

            // To
            foreach ($this->_to as $name => $rcpt)
            {
                if (is_integer($name))
                    $message .= 'To: <' . $rcpt . '>' . self::NL;
                else
                    $message .= 'To: "' . $this->_encode($name) . '"<' . $rcpt . '>' . self::NL;
            }

            // Cc
            foreach ($this->_cc as $name => $rcpt)
            {
                if (is_integer($name))
                    $message .= 'Cc: <' . $rcpt . '>' . self::NL;
                else
                    $message .= 'Cc: "' . $this->_encode($name) . '"<' . $rcpt . '>' . self::NL;
            }

            // Bcc
            foreach ($this->_bcc as $name => $rcpt)
            {
                if (is_integer($name))
                    $message .= 'Bcc: <' . $rcpt . '>' . self::NL;
                else
                    $message .= 'Bcc: "' . $this->_encode($name) . '"<' . $rcpt . '>' . self::NL;
            }

            // Priority
            if ($this->_priority)
                $message .= 'X-Priority: ' . $this->_priority . self::NL;

            // Mailer
            $message .= 'X-mailer: ' . self::MAILER . self::NL;
            $message .= 'X-mailer-author: ' . self::MAILER_AUTHOR . self::NL;

            // Custom headers
            foreach ($this->_headers as $name => $value)
                $message .= $name . ': ' . $value. self::NL;

            // Date
            $message .= 'Date: ' . date('r') . self::NL;

            // Subject
            $message .= 'Subject: ' . $this->_encode($this->_subject) . self::NL;

            // Message
            /*
            The message will contain text and attachments.
            This implementation consider the multipart/mixed method only.
            http://en.wikipedia.org/wiki/MIME#Multipart_messages
            */
            if ($this->_attachments || $this->_raw)
            {
                $separator = hash('sha256', time());
                $message .= 'MIME-Version: 1.0' . self::NL;
                $message .= 'Content-Type: multipart/mixed; boundary=' . $separator . self::NL;
                $message .= self::NL;
                $message .= 'This is a message with multiple parts in MIME format.' . self::NL;
                $message .= '--' . $separator . self::NL;
                $message .= 'Content-Type: ' . $this->_text['Content-Type'] . '; charset=' . $this->_text['charset'] . self::NL;
                $message .= self::NL;
                $message .= $this->_text['body'] . self::NL;
                foreach ($this->_attachments as $name => $attach)
                {
                    $message .= '--' . $separator . self::NL;
                    $message .= 'Content-Disposition: attachment; filename=' . $name . '; modification-date="' . date('r', filemtime($attach['path'])) . '"' . self::NL;
                    if (substr($attach['Content-Type'], 0, 5) == 'text/')
                    {
                        $message .= 'Content-Type: ' . $attach['Content-Type'] . '; charset=' . $attach['charset'] . self::NL;
                        $message .= self::NL;
                        $message .= file_get_contents($attach['path']) . self::NL;
                    }
                    else
                    {
                        $message .= 'Content-Type: ' . $attach['Content-Type'] . self::NL;
                        $message .= 'Content-Transfer-Encoding: base64' . self::NL;
                        $message .= self::NL;
                        $message .= base64_encode(file_get_contents($attach['path'])) . self::NL;
                    }
                }
                foreach ($this->_raw as $name => $raw)
                {
                    $message .= '--' . $separator . self::NL;
                    $message .= 'Content-Disposition: attachment; filename=' . $name . '; modification-date="' . date('r') . '"' . self::NL;
                    if (substr($raw['Content-Type'], 0, 5) == 'text/')
                    {
                        $message .= 'Content-Type: ' . $raw['Content-Type'] . '; charset=' . $raw['charset'] . self::NL;
                        $message .= self::NL;
                        $message .= $raw['content'] . self::NL;
                    }
                    else
                    {
                        $message .= 'Content-Type: ' . $raw['Content-Type'] . self::NL;
                        $message .= 'Content-Transfer-Encoding: base64' . self::NL;
                        $message .= self::NL;
                        $message .= base64_encode($raw['content']) . self::NL;
                    }
                }
                $message .= '--' . $separator . '--' . self::NL;
            }
            else
            {
                $message .= 'Content-Type: ' . $this->_text['Content-Type'] . '; charset=' . $this->_text['charset'] . self::NL;
                $message .= self::NL . $this->_text['body'] . self::NL;
            }
        }
        $message .= '.'; // The _dialog function below will add self::NL;

        // Body!
        $send = $this->_dialog($message, self::OK);

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
