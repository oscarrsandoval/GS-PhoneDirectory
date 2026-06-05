<?php
/**
 * Application configuration.
 *
 * Plain PHP, no Composer. Adjust paths here if you move the data directory
 * above the web root (recommended for production — see README).
 */

declare(strict_types=1);

// Absolute path to the directory that holds the JSON "database" files.
// For hardening you can move this outside the web root, e.g.
//   define('DATA_DIR', '/var/lib/gs-phonedirectory');
define('DATA_DIR', dirname(__DIR__) . '/data');

// JSON data files.
define('CONTACTS_FILE', DATA_DIR . '/contacts.json');
define('USERS_FILE', DATA_DIR . '/users.json');

// The public file that Grandstream phones download. Must be named
// exactly "phonebook.xml" and live somewhere the phones can reach over HTTP(S).
define('XML_PATH', dirname(__DIR__) . '/phonebook.xml');

// Phone number types offered in the UI and written to the XML "type" attribute.
// Grandstream only recognises these three values (case-sensitive).
const PHONE_TYPES = ['Work', 'Home', 'Mobile'];

// The SIP account line the phones use to dial directory contacts. Written as
// <accountindex> for every contact in phonebook.xml. Set this to the account
// index your phones dial out on (commonly 1). Change it here and rebuild.
const PHONE_ACCOUNT_INDEX = 1;

// User roles. "admin" can manage users and contacts; "editor" can manage
// contacts only. Legacy accounts with no role are treated as admin so an
// existing install is never locked out (see current_role()).
const USER_ROLES = ['admin', 'editor'];

// Display name used in page titles / headers.
const APP_NAME = 'GS Phone Directory';

// Session cookie name (kept distinct so it does not clash with other apps).
const SESSION_NAME = 'gsphonedir';
