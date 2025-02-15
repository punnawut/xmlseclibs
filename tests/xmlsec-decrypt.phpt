--TEST--
Basic Decryption
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');
use Punnawut\XMLSecLibs\XMLSecEnc;

/* When we need to locate our own key based on something like a key name */
function locateLocalKey($objKey) {
	/* In this example the key is identified by filename */
	$filename = $objKey->name;
	if (! empty($filename)) {
		$objKey->loadKey(dirname(__FILE__) . "/$filename", TRUE);
	} else {
	    $objKey->loadKey(dirname(__FILE__) . "/privkey.pem", TRUE);
	}
}

$arTests = array('AOESP_SHA1'=>'oaep_sha1-res.xml', 'AES128-GCM'=>'aes128-gcm-res.xml',
	'AES192-GCM'=>'aes192-gcm-res.xml', 'AES256-GCM'=>'aes256-gcm-res.xml');

$doc = new DOMDocument();

foreach ($arTests AS $testName=>$testFile) {
	$output = NULL;
	print "$testName: ";

	// Skip AES tests is PHP < 7.1.0
	if ((substr($testName, 0, 3) === "AES") && (version_compare(PHP_VERSION, '7.1.0') < 0)) {
		print "Passed\n";
		continue;
	}

	$doc->load(dirname(__FILE__) . "/$testFile");
	
	try {
		$objenc = new XMLSecEnc();
		$encData = $objenc->locateEncryptedData($doc);
		if (! $encData) {
			throw new Exception("Cannot locate Encrypted Data");
		}
		$objenc->setNode($encData);
		$objenc->type = $encData->getAttribute("Type");
		if (! $objKey = $objenc->locateKey()) {
			throw new Exception("We know the secret key, but not the algorithm");
		}
		$key = NULL;
		
		if ($objKeyInfo = $objenc->locateKeyInfo($objKey)) {
			if ($objKeyInfo->isEncrypted) {
				$objencKey = $objKeyInfo->encryptedCtx;
				locateLocalKey($objKeyInfo);
				$key = $objencKey->decryptKey($objKeyInfo);
			}
		}
		
		if (! $objKey->key && empty($key)) {
			locateLocalKey($objKey);
		}
		if (empty($objKey->key)) {
			$objKey->loadKey($key);
		}
		
		$token = NULL;

		if ($decrypt = $objenc->decryptNode($objKey, TRUE)) {
			$output = NULL;
			if ($decrypt instanceof DOMNode) {
				if ($decrypt instanceof DOMDocument) {	
					$output = $decrypt->saveXML();
				} else {
					$output = $decrypt->ownerDocument->saveXML();
				}
			} else {
				$output = $decrypt;
			}
		}
	} catch (Exception $e) {
		var_dump($e);
	}

	$outfile = dirname(__FILE__) . "/basic-doc.xml";
	$res = NULL;
	if (file_exists($outfile)) {
	    $resDoc = new DOMDocument();
	    $resDoc->load($outfile);
		$res = $resDoc->saveXML();
		if ($output == $res) {
			print "Passed\n";
			continue;
		}
	}
	print "Failed\n";
	
}
?>
--EXPECTF--
AOESP_SHA1: Passed
AES128-GCM: Passed
AES192-GCM: Passed
AES256-GCM: Passed