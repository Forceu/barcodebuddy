<?php 
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * 
 * Auth helper
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.5
 */



require_once __DIR__ . '/composer/vendor/autoload.php';
require_once __DIR__ . "/../configProcessing.inc.php";

global $CONFIG;
if (!file_exists($CONFIG->AUTHDB_PATH)) 
    createSqlFile();

$db_auth = new \Delight\Db\PdoDsn('sqlite:' . $CONFIG->AUTHDB_PATH);
$auth    = new \Delight\Auth\Auth($db_auth);
\header_remove('X-Frame-Options');


function isUserSetUp(): bool {
	global $auth;
	return $auth->admin()->doesUserHaveRole(1, \Delight\Auth\Role::ADMIN);
}

function createSqlFile(): void {
	global $CONFIG;
    $db = new SQLite3($CONFIG->AUTHDB_PATH);
    $db->exec(getInitialSetupSql());
}

function changeUserName(string $newName): void {
	global $CONFIG;
    $db = new SQLite3($CONFIG->AUTHDB_PATH);
    $db->exec("UPDATE users SET username='$newName'");
}

function getInitialSetupSql(): string {

    return 'PRAGMA foreign_keys=OFF;
			BEGIN TRANSACTION;
			CREATE TABLE IF NOT EXISTS "users" (
				"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL CHECK ("id" >= 0),
				"email" VARCHAR(249) NOT NULL,
				"password" VARCHAR(255) NOT NULL,
				"username" VARCHAR(100) DEFAULT NULL,
				"status" INTEGER NOT NULL CHECK ("status" >= 0) DEFAULT "0",
				"verified" INTEGER NOT NULL CHECK ("verified" >= 0) DEFAULT "0",
				"resettable" INTEGER NOT NULL CHECK ("resettable" >= 0) DEFAULT "1",
				"roles_mask" INTEGER NOT NULL CHECK ("roles_mask" >= 0) DEFAULT "0",
				"registered" INTEGER NOT NULL CHECK ("registered" >= 0),
				"last_login" INTEGER CHECK ("last_login" >= 0) DEFAULT NULL,
				"force_logout" INTEGER NOT NULL CHECK ("force_logout" >= 0) DEFAULT "0",
				CONSTRAINT "email" UNIQUE ("email")
			);
			INSERT INTO users VALUES(1,\'admin@barcode.buddy\',\'$2y$10$6wo6L5ryKKn29t3qALCm1u61Zc2mwyjuY/sK0Qb/o0JVAYuWYUHMy\',\'admin\',0,1,1,0,'.time().',NULL,0);
			CREATE TABLE IF NOT EXISTS "users_confirmations" (
				"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL CHECK ("id" >= 0),
				"user_id" INTEGER NOT NULL CHECK ("user_id" >= 0),
				"email" VARCHAR(249) NOT NULL,
				"selector" VARCHAR(16) NOT NULL,
				"token" VARCHAR(255) NOT NULL,
				"expires" INTEGER NOT NULL CHECK ("expires" >= 0),
				CONSTRAINT "selector" UNIQUE ("selector")
			);
			CREATE TABLE IF NOT EXISTS "users_remembered" (
				"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL CHECK ("id" >= 0),
				"user" INTEGER NOT NULL CHECK ("user" >= 0),
				"selector" VARCHAR(24) NOT NULL,
				"token" VARCHAR(255) NOT NULL,
				"expires" INTEGER NOT NULL CHECK ("expires" >= 0),
				CONSTRAINT "selector" UNIQUE ("selector")
			);
			CREATE TABLE IF NOT EXISTS "users_resets" (
				"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL CHECK ("id" >= 0),
				"user" INTEGER NOT NULL CHECK ("user" >= 0),
				"selector" VARCHAR(20) NOT NULL,
				"token" VARCHAR(255) NOT NULL,
				"expires" INTEGER NOT NULL CHECK ("expires" >= 0),
				CONSTRAINT "selector" UNIQUE ("selector")
			);
			CREATE TABLE IF NOT EXISTS "users_throttling" (
				"bucket" VARCHAR(44) PRIMARY KEY NOT NULL,
				"tokens" REAL NOT NULL CHECK ("tokens" >= 0),
				"replenished_at" INTEGER NOT NULL CHECK ("replenished_at" >= 0),
				"expires_at" INTEGER NOT NULL CHECK ("expires_at" >= 0)
			);
			DELETE FROM sqlite_sequence;
			INSERT INTO sqlite_sequence VALUES(\'users\',1);
			CREATE INDEX "users_confirmations.email_expires" ON "users_confirmations" ("email", "expires");
			CREATE INDEX "users_confirmations.user_id" ON "users_confirmations" ("user_id");
			CREATE INDEX "users_remembered.user" ON "users_remembered" ("user");
			CREATE INDEX "users_resets.user_expires" ON "users_resets" ("user", "expires");
			CREATE INDEX "users_throttling.expires_at" ON "users_throttling" ("expires_at");
			COMMIT;';
}
