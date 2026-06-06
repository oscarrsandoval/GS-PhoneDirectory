<?php
/**
 * Grandstream phonebook.xml generation.
 *
 * Produces the <AddressBook> format Grandstream GXP/GXV/WP phones download.
 * Element names are case-sensitive. XMLWriter handles escaping of special
 * characters (&, <, >, quotes) automatically.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';

/**
 * Build the phonebook XML string from a list of contacts.
 *
 * @param array<int,array<string,mixed>> $contacts
 */
function generate_xml(array $contacts): string
{
    $w = new XMLWriter();
    $w->openMemory();
    $w->setIndent(true);
    $w->setIndentString('  ');
    $w->startDocument('1.0', 'utf-8');

    $w->startElement('AddressBook');
    $w->writeElement('version', '1');

    foreach ($contacts as $contact) {
        $first = trim((string)($contact['first_name'] ?? ''));
        $last = trim((string)($contact['last_name'] ?? ''));
        $company = trim((string)($contact['company'] ?? ''));
        $number = trim((string)($contact['phone'] ?? ''));

        // A contact is only useful to the phone if it has a number and a name.
        if ($number === '' || ($first === '' && $last === '')) {
            continue;
        }

        $type = (string)($contact['type'] ?? 'Work');
        if (!in_array($type, PHONE_TYPES, true)) {
            $type = 'Work';
        }
        // The SIP account line the phones dial out on is a deployment-wide
        // setting, so emit the configured value for every contact rather than
        // any per-contact leftover. Change it once in config.php.
        $accountIndex = (string)PHONE_ACCOUNT_INDEX;

        $w->startElement('Contact');
        $w->writeElement('id', (string)(int)($contact['id'] ?? 0));
        if ($first !== '') {
            $w->writeElement('FirstName', $first);
        }
        if ($last !== '') {
            $w->writeElement('LastName', $last);
        }
        if ($company !== '') {
            $w->writeElement('Company', $company);
        }

        $w->startElement('Phone');
        $w->writeAttribute('type', $type);
        $w->writeElement('phonenumber', $number);
        $w->writeElement('accountindex', $accountIndex);
        $w->endElement(); // Phone

        $w->endElement(); // Contact
    }

    $w->endElement(); // AddressBook
    $w->endDocument();

    $xml = $w->outputMemory();

    // libxml normalises the declared encoding to upper-case ("UTF-8"); rewrite
    // the declaration to lower-case to match Grandstream's sample format.
    return preg_replace(
        '/^<\?xml version="1\.0" encoding="UTF-8"\?>/',
        '<?xml version="1.0" encoding="utf-8"?>',
        $xml,
        1
    );
}

/**
 * Regenerate the public phonebook.xml on disk from contacts.json.
 * Atomic write so phones never download a half-written file.
 */
function rebuild_phonebook(): void
{
    $contacts = read_json(CONTACTS_FILE);
    $xml = generate_xml($contacts);

    $dir = dirname(XML_PATH);
    $tmp = tempnam($dir, 'pbxml');
    if ($tmp === false) {
        throw new RuntimeException('Unable to create temp file for phonebook.xml');
    }

    if (file_put_contents($tmp, $xml, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('Unable to write phonebook.xml');
    }

    if (!rename($tmp, XML_PATH)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to move phonebook.xml into place');
    }

    @chmod(XML_PATH, 0664);
}
