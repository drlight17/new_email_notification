#!/usr/bin/php
<?php
error_reporting(0);
require_once "Zend/Mime/Message.php";
require_once "Zend/Mime/Part.php";
require_once "Zend/Mime.php";
require_once "Zend/Mail/Message.php";
require_once "Zend/Mail.php";
require_once "Zend/Mail/Transport/Smtp.php";
require_once __DIR__.'/vendor/autoload.php';
use Zend_Mail_Message as MailMessage;

$servername = "localhost";
$username = "postfix";
$password = "gilucheviboothoo";
$dbname = "acct";
//samoilov 13.05.2019 for authentificated send back////////////////
//$config = array('auth' => 'login',
//                'username' => 'no-reply@ksc.ru',
//                'password' => 'emuXoorilaquot');
//$transport = new Zend_Mail_Transport_Smtp('mail.ksc.ru', $config);
////////////////////////////////////////////////////////////////////
$parser = new PhpMimeMailParser\Parser();

// read from stdin
$fd = fopen("php://stdin", "r");

//$fd = fopen("/tmp/1", "r");
$email = "";
while (!feof($fd)) {
    $line = fread($fd, 1024);
    $email .= $line;
}
fclose($fd);


$tmpfname = tempnam("/tmp", "postfix_mail_");
$file = fopen("$tmpfname", "w");
fwrite($file, $email);



// *****send notification to sender*****
// Initialize email object using Zend

//$emailObj = new MailMessage(array('raw' => $email));
$parser->setText($email);

$to = $parser->getHeader('to');
$cc = $parser->getHeader('cc');
$bcc = $parser->getHeader('bcc');
$from = $parser->getHeader('from');

$to = $to.','.$cc.','.$bcc;
//echo $to."\n";
//echo $cc."\n";
//echo $bcc."\n";

//$subject = $emailObj->subject;
//echo "FLAG!!!";
$to_array = explode(',', $to);
//print_r($to_array);


$start_from  = strpos($from, '<');

if ($start_from!==false) {
    $from = str_replace('<', '', substr($from, $start_from));
};

$end_from = strpos($from, '>');

if ($end_from!==false) {
    $from = substr($from, 0, $end_from);
};

    //echo $from.',';


foreach ($to_array as $to_ar) {
    $start_to  = strpos($to_ar, '<');
    //echo $start_to;
    if ($start_to!==false) {
        //echo "FLAG!";
        $to_ar = str_replace('<', '', substr($to_ar, $start_to));
    };
    $end_to = strpos($to_ar, '>');
    if ($end_to!==false) {
        $to_ar = substr($to_ar, 0, $end_to);
    };
    //echo $to_ar;
    $to_ar = trim ($to_ar);
    $admksc_check = strpos($to_ar, 'admksc.apatity.ru');
    $isc_check = strpos($to_ar, 'isc.kolasc.net.ru');
    $adm_check = strpos($to_ar, 'adm.kolasc.net.ru');
    if  ($admksc_check!==false || $isc_check!==false || $adm_check!==false) {
    //echo "original message sent to ".$to_ar."\n";
    exec('/usr/sbin/sendmail -i -- '.$to_ar.' <'.$tmpfname, $output, $return);
    //$return=1;
    if ($return != 0) {
        //$tmpfname = tempnam("/tmp", "postfix_mail_");
        //date_default_timezone_set('Etc/GMT+3');
        $date=date("Y-m-d H:i:s");
        $file_log = fopen("/var/log/notifications/error.log", "a");
        fwrite($file_log, $date.' An error while parsing email. Look at '.$tmpfname . PHP_EOL);
        $file_bak = fopen ("$tmpfname".'.bak', "w");
        fwrite ($file_bak, $email);
        fclose($file_log);
        fclose($file_bak);
        // error occurred
    } else {
        // success

    //echo shell_exec('/usr/sbin/sendmail -i -- '.$to_ar.' <'.$tmpfname);



// *****send original email to recipient*****
//echo shell_exec('/usr/sbin/sendmail -i -- '.$to.' <'.$tmpfname);
//echo shell_exec('/usr/sbin/sendmail -oi -t < '.$tmpfname.'');


// fetch new email address through mysql

// Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    //$sql = "SELECT rcpt,enable FROM mail_remote_aliases where alias like '%".$to_ar."%'";
    $sql = "SELECT rcpt,enable FROM mail_remote_aliases where alias like '".$to_ar."%'";
    $result = $conn->query($sql);
    $conn->close();
    //echo 'flag';
    //echo $sql."\n";
    if ($result->num_rows > 0) {

        // output data of each row
        while($row = $result->fetch_assoc()) {
            //echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
            $new_to = $row["rcpt"];
            $enabled = $row["enable"];

            $new_to_pos=strrpos($new_to, ',');
            if ($new_to_pos!==false) {
                $new_to_pos = $new_to_pos + 1;
            }
            $new_to = substr($new_to, $new_to_pos);
            //echo $new_to.',';
            //echo $enabled.',';
            if ($enabled <> 0) {
                //echo "alert sent to ".$from."\n";
                //echo $to_ar." has new address ".$new_to."\n";
            $mail = new Zend_Mail('UTF-8');
            $mail->setBodyHtml('<p>Здравствуйте! </p></p>У пользователя, которому Вы только что отправили письмо, <a href=mailto:'.$to_ar.'>'.$to_ar.'</a>, новый адрес электронной почты: <a href=mailto:'.$new_to.'>'.$new_to.'</a>. Ваше письмо было доставлено по этому <a href=mailto:'.$new_to.'>новому адресу</a>. Просьба в дальнейшем для связи использовать его!</p><p>Это письмо сформировано автоматически, отвечать на него <strong>не нужно!</strong></p><p>По техническим вопросам, связанным с функционированием электронной почты <a href=https://www.ksc.ru>ФИЦ КНЦ РАН</a>, просьба писать <a href=mailto:root@ksc.ru>системному администратору</a>.</p><br>--<br>C уважением, почтовая служба <a href=https://www.ksc.ru>ФИЦ КНЦ РАН</a><br> <font color="#104E8B">___________________________________________________</font>
            <br>
            <a href="http://www.ksc.ru"><img
             src="data:image/png;base64,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"
                alt="www.ksc.ru" height="67" width="295"></a>
');
            //$mail->setFrom('no-reply@admksc.apatity.ru');
            $mail->setFrom($new_to);
            $mail->addTo($from);
            $mail->setSubject('Новый адрес электронной почты у адресата '.$to_ar);
    //samoilov 13.05.2019 for authentificated send back////////////////
    //samoilov banned 16.05.2019 because of white list problem
    //        $mail->send($transport);
    ///////////////////////////////////////////////////////////////////
            $mail->send();
            };
        };
    };
};
};
            };
fclose($file);
unlink($tmpfname);
?>
