<?php

namespace JeroenDesloovere\VCard;

/*
 * This file is part of the VCard PHP Class from Jeroen Desloovere.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use JeroenDesloovere\VCard\Exception as VCardException;
use Behat\Transliterator\Transliterator;

/**
 * VCard PHP Class to generate .vcard files and save them to a file or output as a download.
 *
 * @author Jeroen Desloovere <info@jeroendesloovere.be>
 */
class VCard
{
    /**
     * Filename
     *
     * @var string
     */
    private $filename;

    /**
     * Properties
     *
     * @var array
     */
    private $properties;

    /**
     * Default Charset
     *
     * @var string
     */
    public $charset = 'utf-8';

    /**
     * Add address
     *
     * @return void
     * @param  string[optional] $name
     * @param  string[optional] $extended
     * @param  string[optional] $street
     * @param  string[optional] $city
     * @param  string[optional] $region
     * @param  string[optional] $zip
     * @param  string[optional] $country
     * @param  string[optional] $type
     *    $type may be DOM | INTL | POSTAL | PARCEL | HOME | WORK
     *    or any combination of these: e.g. "WORK;PARCEL;POSTAL"
     */
    public function addAddress(
        $name = '',
        $extended = '',
        $street = '',
        $city = '',
        $region = '',
        $zip = '',
        $country = '',
        $type = 'WORK;POSTAL'
    ) {
        // init value
        $value = $name . ';' . $extended . ';' . $street . ';' . $city . ';' . $region . ';' . $zip . ';' . $country;

        // set property
        $this->setProperty(
            'ADR' . (($type != '') ? ';' . $type : ''),
            $value
        );
    }

    /**
     * Add birthday
     *
     * @return void
     * @param  string $date Format is YYYY-MM-DD
     */
    public function addBirthday($date)
    {
        $this->setProperty('BDAY', $date);
    }

    /**
     * Add company
     *
     * @return void
     * @param  string $company
     */
    public function addCompany($company)
    {
        $this->setProperty('ORG', $company);

        // if filename is empty, add to filename
        if ($this->getFilename() === null) {
            $this->setFilename($company);
        }
    }

    /**
     * Add email
     *
     * @return void
     * @param  string $address The e-mailaddress
     */
    public function addEmail($address)
    {
        $this->setProperty('EMAIL;INTERNET', $address);
    }

    /**
     * Add jobtitle
     *
     * @return void
     * @param  string $jobtitle The jobtitle for the person.
     */
    public function addJobtitle($jobtitle)
    {
        $this->setProperty('TITLE', $jobtitle);
    }

    /**
     * Add a photo or logo (depending on property name)
     *
     * @return boolean
     * @param  string $property LOGO|PHOTO
     * @param  string $url      image url or filename
     * @param  bool   $include   Do we include the image in our vcard or not?
     */
    private function addMedia($property, $url, $include = true)
    {
        if ($include) {
            $value = file_get_contents($url);

            // nothing returned from URL, stop here
            if (!$value) {
                return false;
            }

            // introduced in PHP 5.4
            if (function_exists('getimagesizefromstring')) {
                $imginfo = getimagesizefromstring($value);
            } else {
                $imginfo = getimagesize('data://application/octet-stream;base64,' . base64_encode($value));
            }

            // mime type found
            if (array_key_exists('mime', $imginfo)) {
                $type = strtoupper(str_replace('image/', '', $imginfo['mime']));
                // returned data doesn't have a MIME type
            } else {
                return false;
            }

            $value = base64_encode($value);
            $property .= ";ENCODING=b;TYPE=" . $type;
        } else {
            $value = $url;
        }

        $this->setProperty($property, $value);
        return true;
    }

    /**
     * Add name
     *
     * @return void
     * @param  string[optional] $lastName
     * @param  string[optional] $firstName
     * @param  string[optional] $additional
     * @param  string[optional] $prefix
     * @param  string[optional] $suffix
     */
    public function addName(
        $lastName = '',
        $firstName = '',
        $additional = '',
        $prefix = '',
        $suffix = ''
    ) {
        // define values with non-empty values
        $values = array_filter(array(
            $prefix,
            $firstName,
            $additional,
            $lastName,
            $suffix,
        ));

        // define filename
        $this->setFilename($values);

        // set property
        $property = $lastName . ';' . $firstName . ';' . $additional . ';' . $prefix . ';' . $suffix;
        $this->setProperty('N', $property);

        // is property FN set?
        if (!isset($this->properties['FN']) || $this->properties['FN'] == '') {
            // set property
            $this->setProperty(
                'FN',
                trim(implode(' ', $values))
            );
        }
    }

    /**
     * Add note
     *
     * @return void
     * @param  string $note
     */
    public function addNote($note)
    {
        $this->setProperty('NOTE', $note);
    }

    /**
     * Add phone number
     *
     * @return void
     * @param  string           $number
     * @param  string[optional] $type
     *    Type may be PREF | WORK | HOME | VOICE | FAX | MSG |
     *    CELL | PAGER | BBS | CAR | MODEM | ISDN | VIDEO
     *    or any senseful combination, e.g. "PREF;WORK;VOICE"
     */
    public function addPhoneNumber($number, $type = '')
    {
        $this->setProperty(
            'TEL' . (($type != '') ? ';' . $type : ''),
            $number
        );
    }

    /**
     * Add Photo
     *
     * @return void
     * @param  string $url    image url or filename
     * @param  bool   $include Include the image in our vcard?
     */
    public function addPhoto($url, $include = true)
    {
        $this->addMedia('PHOTO', $url, $include);
    }

    /**
     * Add URL
     *
     * @return void
     * @param  string           $url
     * @param  string[optional] $type Type may be WORK | HOME
     */
    public function addURL($url, $type = '')
    {
        $this->setProperty(
            'URL' . (($type != '') ? ';' . $type : ''),
            $url
        );
    }

    /**
     * Build VCard (.vcf)
     *
     * @return string
     */
    public function buildVCard()
    {
        // init string
        $string = "BEGIN:VCARD\r\n";
        $string .= "VERSION:3.0\r\n";
        $string .= "REV:" . date("Y-m-d") . "T" . date("H:i:s") . "Z\r\n";

        // loop all properties
        foreach ($this->properties as $key => $value) {
            // add to string
            $string .= $this->fold($key . ':' . $value . "\r\n");
        }

        // add to string
        $string .= "END:VCARD\r\n";

        // return
        return $string;
    }

    /**
     * Build VCalender (.ics) - Safari (< iOS 8) can not open .vcf files, so we have build a workaround.
     *
     * @return string
     */
    public function buildVCalendar()
    {
        // init dates
        $dtstart = date("Ymd") . "T" . date("Hi") . "00";
        $dtend = date("Ymd") . "T" . date("Hi") . "01";

        // init string
        $string = "BEGIN:VCALENDAR\n";
        $string .= "VERSION:2.0\n";
        $string .= "BEGIN:VEVENT\n";
        $string .= "DTSTART;TZID=Europe/London:" . $dtstart . "\n";
        $string .= "DTEND;TZID=Europe/London:" . $dtend . "\n";
        $string .= "SUMMARY:Click attached contact below to save to your contacts\n";
        $string .= "DTSTAMP:" . $dtstart . "Z\n";
        $string .= "ATTACH;VALUE=BINARY;ENCODING=BASE64;FMTTYPE=text/directory;\n";
        $string .= " X-APPLE-FILENAME=" . $this->getFilename() . "." . $this->getFileExtension() . ":\n";

        // base64 encode it so that it can be used as an attachemnt to the "dummy" calendar appointment
        $b64vcard = base64_encode($this->buildVCard());

        // chunk the single long line of b64 text in accordance with RFC2045
        // (and the exact line length determined from the original .ics file exported from Apple calendar
        $b64mline = chunk_split($b64vcard, 74, "\n");

        // need to indent all the lines by 1 space for the iphone (yes really?!!)
        $b64final = preg_replace('/(.+)/', ' $1', $b64mline);
        $string .= $b64final;

        // output the correctly formatted encoded text
        $string .= "END:VEVENT\n";
        $string .= "END:VCALENDAR\n";

        // return
        return $string;
    }

    /**
     * Decode
     *
     * @return string decoded
     * @param  string $value The value to decode
     */
    private function decode($value)
    {
        // convert cyrlic, greek or other caracters to ASCII characters
        return Transliterator::transliterate($value);
    }

    public function getHeaders()
    {
        $output = $this->getOutput();

        return array(
            'Content-type: ' . $this->getContentType() . '; charset=' . $this->charset,
            'Content-Disposition: attachment; filename=' . $this->getFilename() . '.' . $this->getFileExtension(),
            'Content-Length: ' . strlen($output),
            'Connection: close'
        );
    }

    /**
     * Download a vcard or vcal file to the browser.
     */
    public function download()
    {
        // define output
        $output = $this->getOutput();

        foreach($this->getHeaders() as $header)
        {
            header($header);
        }

        // echo the output and it will be a download
        echo $output;
    }

    /**
     * Get content type
     *
     * @return string
     */
    public function getContentType()
    {
        return ($this->isIOS7()) ?
            'text/x-vcalendar' : 'text/x-vcard'
            ;
    }

    /**
     * Get output as string
     * @deprecated in the future
     *
     * @return string
     */
    public function get()
    {
        return $this->getOutput();
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Get file extension
     *
     * @return string
     */
    public function getFileExtension()
    {
        return ($this->isIOS7()) ?
            'ics' : 'vcf'
            ;
    }

    /**
     * Get output as string
     * iOS devices (and safari < iOS 8 in particular) can not read .vcf (= vcard) files.
     * So I build a workaround to build a .ics (= vcalender) file.
     *
     * @return string
     */
    public function getOutput()
    {
        return ($this->isIOS7()) ?
            $this->buildVCalendar() : $this->buildVCard()
            ;
    }

    /**
     * Is iOS less than 7 (to should cal be returned)
     *
     * @return bool
     */
    public function isIOS7()
    {
        if($this->isIOS() && $this->shouldAttachmentBeCal()){
            return true;
        }

        return false;
    }

    /**
     * Is iOS - Check if the user is using an iOS-device
     *
     * @return bool
     */
    public function isIOS()
    {
        // get user agent
        $browser = strtolower($_SERVER['HTTP_USER_AGENT']);

        return (strpos($browser, 'iphone') || strpos($browser, 'ipod') || strpos($browser, 'ipad'));
    }

    /**
     * checks if we should return vcard in cal wrapper
     *
     * @return bool
     */
    protected function shouldAttachmentBeCal()
    {
        $browser = strtolower($_SERVER['HTTP_USER_AGENT']);

        $matches = [];
        preg_match('/os (\d+)_(\d+)\s+/', $browser, $matches);
        $version = isset($matches[1]) ? ((int)$matches[1]) : 999;

        if($version < 8){
            return true;
        }

        return false;
    }

    /**
     * Save to a file
     *
     * @return void
     */
    public function save()
    {
        $file = $this->getFilename() . '.' . $this->getFileExtension();

        file_put_contents(
            $file,
            $this->getOutput()
        );
    }

    /**
     * Set filename
     *
     * @return void
     * @param  mixed  $value
     * @param  bool   $overwrite[optional] Default overwrite is true
     * @param  string $separator[optional] Default separator is an underscore '_'
     */
    public function setFilename($value, $overwrite = true, $separator = '_')
    {
        // recast to string if $value is array
        if (is_array($value)) {
            $value = implode($separator, $value);
        }

        // trim unneeded values
        $value = trim($value, $separator);

        // remove all spaces
        $value = preg_replace('/\s+/', $separator, $value);

        // if value is empty, stop here
        if (empty($value)) {
            return;
        }

        // decode value + lowercase the string
        $value = strtolower($this->decode($value));

        // urlize this part
        $value = Transliterator::urlize($value);

        // overwrite filename or add to filename using a prefix in between
        $this->filename = ($overwrite) ?
            $value : $this->filename . $separator . $value;
    }

    /**
     * Set property
     *
     * @return void
     * @param  string $key
     * @param  string $value
     */
    private function setProperty($key, $value)
    {
        $this->properties[$key] = $value;
    }

    /**
     * Fold a line according to RFC2425 section 5.8.1.
     *
     * @link http://tools.ietf.org/html/rfc2425#section-5.8.1
     * @param string $text
     * @return mixed
     */
    protected function fold($text)
    {
        if (strlen($text) <= 75) {
            return $text;
        }

        // split, wrap and trim trailing separator
        return substr(chunk_split($text, 73, "\r\n "), 0, -3);
    }
}
