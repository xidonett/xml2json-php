<?php

namespace App\Http\Controllers;

use ABGEO\XmlToJson\StringConverter;
use DOMDocument;
use DOMElement;
use DOMException;
use http\Env\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

use stdClass;

class Converter
{
    public function buildXML($dom, $tag, $val)
    {
        $xml = null;
        if (is_scalar($val)) {
            $xml = $dom->createElement($tag, $val);
        } else if (is_array($val)) {
            $xml = $dom->createElement($tag);
            foreach ($val as $element) {
                $xml->appendChild($this->buildXML($dom,"element",$element));
            }
        } else if (is_object($val)) {
            $xml = $dom->createElement($tag);
            foreach ((array)$val as $key=>$property) {
                $key = $this->keyToTagASCII($key);
                try {
                    new DOMElement($key);
                    $xml->appendChild($this->buildXML($dom,$key,$property));
                } catch(DOMException $e) {
                    abort('500', "Invalid key name: (".$key.") - ".$e->getMessage()."\n");
                }
            }
        } else {
            $xml = new DOMElement($tag);
        }
        return $xml;
    }

    public function keyToTagASCII($key)
    {
        if (preg_match("/^xml/i", $key)) {
            $key = "_".$key;
        }
        if (preg_match("/^[0-9\.-]/", $key)) {
            $key = "_".$key;
        }
        $key = preg_replace("/[^0-9a-zA-Z\.\-_]/", "_", $key);
        return $key;
    }

    public function getContent(Request $request)
    {
        $file = "";

        if ( $request->file('file') ) {
            $file = file_get_contents($request->file('file')->getRealPath());
        } elseif( $request->post('self_input') ) {
            $file = $request->post('self_input');
        }

        return $file;
    }

    public function getXML(Request $request)
    {
        $this->requestValidation($request, 'xml');

        $val = json_decode($this->getContent($request));

        if (json_last_error() == JSON_ERROR_NONE) {
            $dom = new DOMDocument('1.0');
            $dom->formatOutput = true;
            $dom->appendChild($this->buildXML($dom, "root", $val));
            print($dom->saveXML());
        } else {
            abort('500', "json_decode() error: ".json_last_error_msg());
        }
    }

    public function getJSON(Request $request)
    {
        $this->requestValidation($request, 'json');

        $converter = new StringConverter();
        $jsonContent = $converter->convert($this->getContent($request));

        print($jsonContent);
    }
}
