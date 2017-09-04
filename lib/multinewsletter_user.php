<?php
/**
 * Benutzer des MultiNewsletters.
 */
class MultinewsletterUser {
	/**
	 * @var int Unique BenutzerID .
	 */
	var $user_id = 0;

	/**
	 * @var String Unique E-Mailadresse.
	 */
	var $email = "";

	/**
	 * @var String Akademischer Grad des Benutzers (z.B. Dr. oder Prof.)
	 */
	var $grad = "";

	/**
	 * @var String Vorname.
	 */
	var $firstname = "";

	/**
	 * @var String Nachname.
	 */
	var $lastname = "";

	/**
	 * @var int Anrede. 0 ist die männliche Anrede, 1 die weibliche
	 */
	var $title = 0;

	/**
	 * @var int Redaxo SprachID.
	 */
	var $clang_id = 0;

	/**
	 * @var int Status des Abonnements: 0 für inaktiv, 1 für aktiv
	 */
	var $status = 0;

	/**
	 * @var int[] Array mit ID's der abonnierten Newsletter Gruppen
	 */
	var $group_ids = [];

	/**
	 * @var boolean Steht der Benutzer in der aktuellen Warteschlange des zu
	 * sendenden Newsletters.
	 */
	var $send_archive_id = 0;

	/**
	 * @var int Unix Datum der Erstellung des Datensatzes in der Datenbank.
	 */
	var $createdate = 0;

	/**
	 * @var String IP Adresse von der aus der Datensatz erstellt wurde.
	 */
	var $createIP = "0.0.0.0";

	/**
	 * @var int Unixdatum der Bestätigung des Abonnements
	 */
	var $activationdate = 0;

	/**
	 * @var String IP Adresse von der aus die Bestätigung vorgenommen wurde.
	 */
	var $activationIP = "0.0.0.0";

	/**
	 * @var int Unixdatum der letzten Aktualisierung des Datensatzes
	 */
	var $updatedate = 0;

	/**
	 * @var String IP Adresse von der die letzte Aktualisierung vorgenommen wurde.
	 */
	var $updateIP = "0.0.0.0";

	/**
	 * @var String Mailchimp ID
	 */
	var $mailchimp_id = null;

	/**
	 * @var String Art der Anmeldung zum Newsletter: web, import oder backend
	 */
	var $subscriptiontype = "";

	/**
	 * @var String 6-stelliger Anmeldeschlüssel für die Bestätigung
	 */
	var $activationkey = 0;

	/**
	 * Stellt die Daten des Benutzers aus der Datenbank zusammen.
	 * @param int $user_id UserID aus der Datenbank.
	 */
	 public function __construct($user_id) {
		$this->user_id = $user_id;

		if($user_id > 0) {
			$query = "SELECT * FROM ". rex::getTablePrefix() ."375_user "
					."WHERE user_id = ". $this->user_id ." "
					."LIMIT 0, 1";
			$result = rex_sql::factory();
			$result->setQuery($query);
			$num_rows = $result->getRows();

			if($num_rows > 0) {
				$this->email = trim($result->getValue("email"));
				$this->grad = stripslashes($result->getValue("grad"));
				$this->firstname = stripslashes($result->getValue("firstname"));
				$this->lastname = stripslashes($result->getValue("lastname"));
				$this->title = $result->getValue("title");
				$this->clang_id = $result->getValue("clang_id");
				$this->status = $result->getValue("status");
				$this->group_ids = preg_grep('/^\s*$/s', explode("|", $result->getValue("group_ids")), PREG_GREP_INVERT);
				$this->send_archive_id = $result->getValue("send_archive_id");
				$this->createdate = $result->getValue("createdate");
				$this->createIP = htmlspecialchars_decode($result->getValue("createip"));
				$this->activationdate = $result->getValue("activationdate");
				$this->activationIP = htmlspecialchars_decode($result->getValue("activationip"));
				$this->updatedate = $result->getValue("updatedate");
				$this->updateIP = $result->getValue("updateip");
				$this->mailchimpID = $result->getValue("mailchimp_id");
				$this->subscriptiontype = $result->getValue("subscriptiontype");
				$this->activationkey = htmlspecialchars_decode($result->getValue("activationkey"));
			}
		}
	}

	/**
	 * Erstellt einen ganz neuen Nutzer.
	 * @param String $email E-Mailadresse des Nutzers
	 * @param int $title Anrede (0 = männlich, 1 = weiblich)
	 * @param String $grad Akademischer Grad des Nutzers
	 * @param String $firstname Vorname des Nutzers
	 * @param String $lastname Nachname des Nutzers
	 * @param int $clang_id Redaxo SprachID des Nutzers
	 * @return MultinewsletterUser Intialisiertes MultinewsletterUser Objekt.
	 */
	public static function factory($email, $title, $grad, $firstname, $lastname, $clang_id) {
		$user = new MultinewsletterUser(0);
		$user->email = $email;
		$user->title = $title;
		$user->grad = $grad;
		$user->firstname = $firstname;
		$user->lastname = $lastname;
		$user->clang_id = $clang_id;
		$user->status = 1;
		$user->createdate = time();
		$user->createIP = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);

		return $user;
	}

	/**
	 * Aktiviert den Benutzer, d.h. der Activationkey wird gelöscht und der Status
	 * auf aktiv gesetzt.
	 */
	public function activate() {
		$this->activationkey = 0;
		$this->activationdate = time();
		$this->activationIP = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
		$this->status = 1;
		$this->save();

        rex_extension::registerPoint(new rex_extension_point('multinewsletter.userActivated', $this));

		$this->sendAdminNoctificationMail("subscribe");
	}

	/**
	 * Löscht den Benutzer aus der Datenbank.
	 */
	public function delete() {
        if (MultinewsletterMailchimp::isActive()) {
            $Mailchimp = MultinewsletterMailchimp::factory();

            try {
                foreach ($this->group_ids as $group_id) {
                    $Group = new MultinewsletterGroup($group_id);

                    if (strlen($Group->mailchimp_list_id)) {
                        $Mailchimp->unsubscribe($this, $Group->mailchimp_list_id);
                    }
                }
            }
            catch (MultinewsletterMailchimpException $ex) {
            }
        }
		$query = "DELETE FROM ". rex::getTablePrefix() ."375_user WHERE user_id = ". $this->user_id;
		$result = rex_sql::factory();
		$result->setQuery($query);
	}

	/**
	 * Holt einen neuen Benutzer anhand der E-Mailadresse aus der Datenbank.
	 * @param String $email E-Mailadresse des Nutzers
	 * @return MultinewsletterUser Intialisiertes MultinewsletterUser Objekt.
	 */
	public static function initByMail($email) {
		$user = new MultinewsletterUser(0);
		$user->email = strtolower($email);

		if($user->email != "") {
			$query = "SELECT * FROM ". rex::getTablePrefix() ."375_user "
					."WHERE email = '". trim($user->email) ."'";
			$result = rex_sql::factory();
			$result->setQuery($query);
			$num_rows = $result->getRows();

			if($num_rows > 0) {
				$user->user_id = $result->getValue("user_id");
				$user->grad = $result->getValue("grad");
				$user->firstname = $result->getValue("firstname");
				$user->lastname = $result->getValue("lastname");
				$user->title = $result->getValue("title");
				$user->clang_id = $result->getValue("clang_id");
				$user->status = $result->getValue("status");
				$user->group_ids = preg_grep('/^\s*$/s', explode("|", $result->getValue("group_ids")), PREG_GREP_INVERT);
				$user->send_archive_id = $result->getValue("send_archive_id");
				$user->createdate = $result->getValue("createdate");
				$user->createIP = $result->getValue("createip");
				$user->activationdate = $result->getValue("activationdate");
				$user->activationIP = $result->getValue("activationip");
				$user->updatedate = $result->getValue("updatedate");
				$user->updateIP = $result->getValue("updateip");
				$user->subscriptiontype = $result->getValue("subscriptiontype");
				$user->activationkey = $result->getValue("activationkey");
                $user->mailchimpID = $result->getValue("mailchimp_id");
			}
			return $user;
		}
		return FALSE;
	}

	/**
	 * Personalisiert einen für die Aktivierungsmail
	 * @param String $content Zu personalisierender Inhalt
	 * @return String Personalisierter String.
	 */
	private function personalize($content) {
		$addon = rex_addon::get("multinewsletter");

		$content = str_replace( "+++EMAIL+++", $this->email, stripslashes($content));
		$content = str_replace( "+++GRAD+++", htmlspecialchars(stripslashes($this->grad), ENT_QUOTES), $content);
		$content = str_replace( "+++LASTNAME+++", htmlspecialchars(stripslashes($this->lastname), ENT_QUOTES), $content);
		$content = str_replace( "+++FIRSTNAME+++", htmlspecialchars(stripslashes($this->firstname), ENT_QUOTES), $content);
		$content = str_replace( "+++TITLE+++", htmlspecialchars(stripslashes($addon->getConfig('lang_'. $this->clang_id ."_title_". $this->title)), ENT_QUOTES), $content);
		$content = preg_replace('/ {2,}/', ' ', $content);

		$subscribe_link = rex::getServer() . trim(trim(rex_getUrl($addon->getConfig('link'), $this->clang_id, array('activationkey' => $this->activationkey, 'email' => rawurldecode($this->email))), "/"), "./");
		return str_replace( "+++AKTIVIERUNGSLINK+++", $subscribe_link, $content);
	}

	/**
	 * Aktualisiert den Benutzer in der Datenbank.
	 */
	public function save() {
		$groups = "";
		if(count($this->group_ids) > 0) {
			$groups = "|". implode("|", $this->group_ids) ."|";
		}
		$email = $this->email;
		if(filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			$email = "";
		}
		$createdate = "0";
		if($this->createdate > 0) {
			$createdate = $this->createdate;
		}
		$activationdate = "0";
		if($this->activationdate > 0) {
			$activationdate = $this->activationdate;
		}
		$query = rex::getTablePrefix() .'375_user SET '
				.'email = "'. strtolower(trim($this->email)) .'", '
				.'grad = "'. addslashes($this->grad) .'", '
				.'firstname = "'. addslashes($this->firstname) .'", '
				.'lastname = "'. addslashes($this->lastname) .'", '
				.'title = "'. $this->title .'", '
				.'clang_id = '. $this->clang_id .', '
				.'`status` = '. $this->status .', '
				.'group_ids = "'. $groups .'", '
				.'send_archive_id = '. $this->send_archive_id .', '
				.'createdate = '. $createdate .', '
				.'createip = "'. htmlspecialchars($this->createIP) .'", '
				.'activationdate = '. $activationdate .', '
				.'activationip = "'. htmlspecialchars($this->activationIP) .'", '
				.'updatedate = '. time() .', '
				.'updateip = "'. filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) .'", '
				.'subscriptiontype = "'. $this->subscriptiontype .'", '
				.'activationkey = "'. htmlspecialchars($this->activationkey) .'" ';

        if (MultinewsletterMailchimp::isActive()) {
            $Mailchimp = MultinewsletterMailchimp::factory();
            $_status = $this->status == 2 ? 'unsubscribed' : ($this->status == 1 ? 'subscribed' : 'pending');

            try {
                foreach ($this->group_ids as $group_id) {
                    $Group = new MultinewsletterGroup($group_id);

                    if (strlen($Group->mailchimp_list_id)) {
                        $result = $Mailchimp->addUserToList($this, $Group->mailchimp_list_id, $_status);
                        $query .= ', mailchimp_id = "'. $result['id'] .'"';
                    }
                }
            }
            catch (MultinewsletterMailchimpException $ex) {
            }
        }

        if($this->user_id == 0) {
            $query = "INSERT INTO ". $query;
        }
        else {
            $query = "UPDATE ". $query ." WHERE user_id = ". $this->user_id;
        }

		$result = rex_sql::factory();
		$result->setQuery($query);
	}

	/**
	 * Sendet eine Mail mit Aktivierungslink an den Abonnenten
	 * @param String $sender_mail Absender der Mail
	 * @param String $sender_name Bezeichnung des Absenders der Mail
	 * @param String $subject Betreff der Mail
	 * @param String $body Inhalt der Mail
	 * @return boolean true, wenn erfolgreich versendet, sonst false
	 */
	public function sendActivationMail($sender_mail, $sender_name, $subject, $body) {
		if(!empty($body) && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false && filter_var($sender_mail, FILTER_VALIDATE_EMAIL) !== false) {
			$mail = new rex_mailer();
			$mail->IsHTML(true);
			$mail->CharSet = "utf-8";
			$mail->From = $sender_mail;
			$mail->FromName = $sender_name;
			$mail->Sender = $sender_mail;

			if(trim($this->firstname) != '' && trim($this->lastname) != '') {
				$mail->AddAddress($this->email, trim($this->firstname) .' '. trim($this->lastname));
			}
			else {
				$mail->AddAddress($this->email);
			}

			$mail->Subject = $this->personalize($subject);
			$mail->Body    = rex_extension::registerPoint(new rex_extension_point('multinewsletter.preSend', $this->personalize($body), [
				'mail' => $mail,
				'user' => $this,
			]));
			return $mail->Send();
		}
		else {
			return false;
		}
	}

	/**
	 * Sendet eine Mail an den Admin als Hinweis, dass ein Benutzerstatus
	 * geändert wurde.
	 * @param String $type entweder "subscribe" oder "unsubscribe"
	 * @return boolean true, wenn erfolgreich versendet, sonst false
	 */
	public function sendAdminNoctificationMail($type) {
		$addon = rex_addon::get('multinewsletter');
		if(filter_var($addon->getConfig('subscribe_meldung_email'), FILTER_VALIDATE_EMAIL) !== false) {
			$mail = new rex_mailer();
			$mail->IsHTML(true);
			$mail->CharSet = "utf-8";
			$mail->From = $addon->getConfig('sender');
			$mail->FromName = $addon->getConfig('lang_'. $this->clang_id ."_sendername");
			$mail->Sender = $addon->getConfig('sender');

			$mail->AddAddress($addon->getConfig('subscribe_meldung_email'));

			if($type == "subscribe") {
				$mail->Subject = "Neue Anmeldung zum Newsletter";
				$mail->Body = "Neue Anmeldung zum Newsletter: ". $this->email;
			}
			else {
				$mail->Subject = "Abmeldung vom Newsletter";
				$mail->Body = "Abmeldung vom Newsletter: ". $this->email;
			}
			return $mail->Send();
		}
		else {
			return false;
		}
	}

	/**
	 * Meldet den Benutzer vom Newsletter ab.
	 * @var action String mit auszuführender Aktion
	 */
	public function unsubscribe($action = "delete") {
		if($action == "delete") {
			$this->delete();
		}
		else {
			// $action = "status_unsubscribed"
			$this->status = 2;
			$this->save();
		}

		$this->sendAdminNoctificationMail("unsubscribe");
	}
}

/**
 * Liste Benutzer des MultiNewsletters.
 */
class MultinewsletterUserList {
	/**
	 * @var MultinewsletterUser[] Array mit Benutzerobjekten.
	 */
	var $users = [];

	/**
	 * Stellt die Daten des Benutzers aus der Datenbank zusammen.
	 * @param Array $user_ids Array mit UserIds aus der Datenbank.
	 */
	 public function __construct($user_ids) {
		foreach($user_ids as $user_id) {
			$this->users[] = new MultinewsletterUser($user_id);
		}
	}

	/**
	 * Exportiert die Benutzerliste als CSV und sendet das Dokument als CSV.
	 */
	public static function countAll() {
		$query = "SELECT COUNT(*) as total FROM ". rex::getTablePrefix() ."375_user ";
		$result = rex_sql::factory();
		$result->setQuery($query);

		return $result->getValue("total");
	}

	/**
	 * Exportiert die Benutzerliste als CSV und sendet das Dokument als CSV.
	 */
	public function exportCSV() {
		$spalten = array('email', 'grad', 'title', 'firstname', 'lastname',
			'clang_id', 'status', 'group_ids', 'createdate', 'createip',
			'activationdate', 'activationip', 'updatedate', 'updateip',
			'subscriptiontype');
		$lines = array(implode(';',$spalten));

		foreach($this->users as $user) {
			$groups = "";
			if(count($user->group_ids) > 0) {
				$groups = "|". implode("|", $user->group_ids) ."|";
			}
			$line = [];
			$line[] = $user->email;
			$line[] = $user->grad;
			$line[] = $user->title;
			$line[] = $user->firstname;
			$line[] = $user->lastname;
			$line[] = $user->clang_id;
			$line[] = $user->status;
			$line[] = $groups;
			$line[] = $user->createdate;
			$line[] = $user->createIP;
			$line[] = $user->activationdate;
			$line[] = $user->activationIP;
			$line[] = $user->updatedate;
			$line[] = $user->updateIP;
			$line[] = $user->subscriptiontype;

			$lines[] = implode(';', $line);
		}

		$content = implode("\n", $lines);

		header("Cache-Control: public");
		header("Content-Description: File Transfer");
		header('Content-disposition: attachment; filename=multinewsletter_user.csv');
		header("Content-Type: application/csv");
		header("Content-Transfer-Encoding: binary");
		header('Content-Length: '. strlen($content));
		print($content);
		exit;
	}
}