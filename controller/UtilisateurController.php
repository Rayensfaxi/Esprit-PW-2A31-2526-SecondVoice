<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../model/utilisateur.php';
require_once __DIR__ . '/SmtpMailer.php';
require_once __DIR__ . '/BrevoMailer.php';

class UtilisateurController
{
    private PDO $conn;

    private const ALLOWED_ROLES = ['admin', 'agent', 'client'];
    private const ALLOWED_ACCOUNT_STATUS = ['actif', 'bloque', 'en_pause'];
    private const EMAIL_VERIFY_TOKEN_HOURS = 24;
    private const PASSWORD_RESET_TOKEN_HOURS = 2;
    private static bool $authSchemaChecked = false;

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->conn = $connection;
            $this->ensureAuthSchema();
            return;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
            $this->conn = $GLOBALS['conn'];
            $this->ensureAuthSchema();
            return;
        }

        if (class_exists('Config') && method_exists('Config', 'getConnexion')) {
            $configConnection = Config::getConnexion();
            if ($configConnection instanceof PDO) {
                $this->conn = $configConnection;
                $this->ensureAuthSchema();
                return;
            }
        }

        throw new RuntimeException('Connexion base de donnees indisponible.');
    }

    public function addUser(string $nom, string $prenom, string $email, string $password, string $telephone, string $role, string $statutCompte = 'actif'): int
    {
        $data = $this->validatePayload([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $password,
            'telephone' => $telephone,
            'role' => $role,
            'statut_compte' => $statutCompte
        ], false);

        if ($this->emailExists($data['email'])) {
            throw new InvalidArgumentException('Un utilisateur existe deja avec cet e-mail.');
        }

        $utilisateur = $this->buildUtilisateurFromPayload($data);

        $sql = 'INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, telephone, role, statut_compte, date_creation)
                VALUES (:nom, :prenom, :email, :password, :telephone, :role, :statut_compte, :date_creation)';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nom' => $utilisateur->getNom(),
            ':prenom' => $utilisateur->getPrenom(),
            ':email' => $utilisateur->getEmail(),
            ':password' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':telephone' => $utilisateur->getTelephone(),
            ':role' => $utilisateur->getRole(),
            ':statut_compte' => $data['statut_compte'],
            ':date_creation' => date('Y-m-d')
        ]);

        $userId = (int) $this->conn->lastInsertId();
        if ($data['role'] !== 'client') {
            $this->setEmailVerified($userId, true);
        }

        return $userId;
    }

    public function getUsers(array $filters = []): array
    {
        $sql = 'SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation FROM utilisateur WHERE 1=1';
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (nom LIKE :search OR prenom LIKE :search OR email LIKE :search OR telephone LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $roleFilter = $this->normalizeRole((string) ($filters['role'] ?? ''));
        if ($roleFilter !== '' && $roleFilter !== 'tout' && in_array($roleFilter, self::ALLOWED_ROLES, true)) {
            $sql .= ' AND role = :role';
            $params[':role'] = $roleFilter;
        }

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->normalizeUserRow($row), $rows);
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation FROM utilisateur WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeUserRow($row) : null;
    }

    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation FROM utilisateur WHERE email = :email');
        $stmt->execute([':email' => trim(strtolower($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeUserRow($row) : null;
    }

    public function getUserEntityById(int $id): ?Utilisateur
    {
        $row = $this->getUserById($id);
        return $row ? $this->buildUtilisateurFromRow($row) : null;
    }

    public function authenticateUser(string $email, string $password): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, telephone, role, statut_compte, date_creation, email_verifie, mot_de_passe FROM utilisateur WHERE email = :email');
        $stmt->execute([':email' => trim(strtolower($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        if ((int) ($user['email_verifie'] ?? 0) !== 1) {
            throw new RuntimeException("Votre e-mail n'est pas encore verifie. Consultez votre boite mail.");
        }

        if (strtolower((string) ($user['statut_compte'] ?? 'actif')) !== 'actif') {
            throw new RuntimeException('Ce compte est inactif ou bloque.');
        }

        $hash = (string) ($user['mot_de_passe'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        unset($user['mot_de_passe']);
        return $this->normalizeUserRow($user);
    }

    public function sendEmailVerification(int $userId, string $email, string $displayName, string $verifyPageUrl): bool
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Utilisateur invalide pour verification.');
        }

        $token = $this->createToken('email_verification_tokens', $userId, self::EMAIL_VERIFY_TOKEN_HOURS);
        $baseUrl = $this->encodeUrlForEmail($verifyPageUrl);
        $link = $baseUrl . '?token=' . urlencode($token);

        $name = trim($displayName) !== '' ? trim($displayName) : 'Utilisateur';
        $subject = 'Verification de votre e-mail SecondVoice';
        $body = "Bonjour {$name},\n\n"
            . "Merci pour votre inscription sur SecondVoice.\n"
            . "Cliquez sur ce lien pour verifier votre e-mail:\n{$link}\n\n"
            . "Ce lien expire dans " . self::EMAIL_VERIFY_TOKEN_HOURS . " heures.\n"
            . "Si vous n'etes pas a l'origine de cette demande, ignorez ce message.\n";

        return $this->sendEmail($email, $subject, $body);
    }

    public function verifyEmailByToken(string $token): bool
    {
        $tokenHash = $this->normalizeTokenHash($token);
        $row = $this->getActiveTokenRow('email_verification_tokens', $tokenHash);
        if (!$row) {
            return false;
        }

        $userId = (int) $row['user_id'];
        $this->conn->beginTransaction();
        try {
            $this->setEmailVerified($userId, true);

            $stmt = $this->conn->prepare(
                "UPDATE utilisateur
                 SET statut_compte = CASE WHEN statut_compte = 'en_pause' THEN 'actif' ELSE statut_compte END
                 WHERE id = :id"
            );
            $stmt->execute([':id' => $userId]);

            $this->markTokenAsUsed('email_verification_tokens', $tokenHash);
            $this->conn->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $exception;
        }
    }

    public function requestPasswordReset(string $email, string $resetPageUrl): bool
    {
        $email = trim(strtolower($email));
        $stmt = $this->conn->prepare('SELECT id, nom, prenom, email, email_verifie, statut_compte FROM utilisateur WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if ((int) ($user['email_verifie'] ?? 0) !== 1) {
            return false;
        }

        if (strtolower((string) ($user['statut_compte'] ?? '')) !== 'actif') {
            return false;
        }

        $userId = (int) $user['id'];
        $token = $this->createToken('password_reset_tokens', $userId, self::PASSWORD_RESET_TOKEN_HOURS);
        $baseUrl = $this->encodeUrlForEmail($resetPageUrl);
        $link = $baseUrl . '?token=' . urlencode($token);

        $displayName = trim(((string) ($user['prenom'] ?? '')) . ' ' . ((string) ($user['nom'] ?? '')));
        if ($displayName === '') {
            $displayName = 'Utilisateur';
        }

        $subject = 'Reinitialisation de mot de passe - SecondVoice';
        $body = "Bonjour {$displayName},\n\n"
            . "Vous avez demande la reinitialisation de votre mot de passe.\n"
            . "Cliquez sur ce lien pour definir un nouveau mot de passe:\n{$link}\n\n"
            . "Ce lien expire dans " . self::PASSWORD_RESET_TOKEN_HOURS . " heures.\n"
            . "Si vous n'avez pas fait cette demande, ignorez ce message.\n";

        return $this->sendEmail((string) ($user['email'] ?? $email), $subject, $body);
    }

    public function resetPasswordByToken(string $token, string $newPassword): bool
    {
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('Le mot de passe doit contenir au moins 6 caracteres.');
        }

        $tokenHash = $this->normalizeTokenHash($token);
        $row = $this->getActiveTokenRow('password_reset_tokens', $tokenHash);
        if (!$row) {
            return false;
        }

        $userId = (int) $row['user_id'];
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('UPDATE utilisateur SET mot_de_passe = :password WHERE id = :id');
            $stmt->execute([
                ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => $userId
            ]);

            $this->markTokenAsUsed('password_reset_tokens', $tokenHash);
            $this->conn->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $exception;
        }
    }

    public function updateUser(
        int $id,
        string $nom,
        string $prenom,
        string $email,
        string $telephone,
        string $role,
        ?string $password = null,
        ?string $statutCompte = null
    ): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Identifiant utilisateur invalide.');
        }

        $data = $this->validatePayload([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $password ?? '',
            'telephone' => $telephone,
            'role' => $role,
            'statut_compte' => $statutCompte ?? 'actif'
        ], true);

        if ($this->emailExists($data['email'], $id)) {
            throw new InvalidArgumentException('Cet e-mail est deja utilise par un autre utilisateur.');
        }

        $utilisateur = $this->buildUtilisateurFromPayload($data, $id);

        $sql = 'UPDATE utilisateur
                SET nom = :nom,
                    prenom = :prenom,
                    email = :email,
                    telephone = :telephone,
                    role = :role,
                    statut_compte = :statut_compte';

        $params = [
            ':nom' => $utilisateur->getNom(),
            ':prenom' => $utilisateur->getPrenom(),
            ':email' => $utilisateur->getEmail(),
            ':telephone' => $utilisateur->getTelephone(),
            ':role' => $utilisateur->getRole(),
            ':statut_compte' => $data['statut_compte'],
            ':id' => $id
        ];

        if ($data['password'] !== '') {
            $sql .= ', mot_de_passe = :password';
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteUser(int $id): bool
    {
        throw new RuntimeException('Suppression des utilisateurs desactivee. Utilisez le statut du compte.');
    }

    private function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM utilisateur WHERE email = :email';
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :excludeId';
            $params[':excludeId'] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeRole(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === 'user') {
            return 'client';
        }
        return $normalized;
    }

    private function buildUtilisateurFromPayload(array $payload, ?int $id = null): Utilisateur
    {
        return new Utilisateur(
            (string) ($payload['nom'] ?? ''),
            (string) ($payload['prenom'] ?? ''),
            (string) ($payload['email'] ?? ''),
            (string) ($payload['password'] ?? ''),
            (string) ($payload['telephone'] ?? ''),
            $this->normalizeRole((string) ($payload['role'] ?? 'client')),
            $id
        );
    }

    private function buildUtilisateurFromRow(array $row): Utilisateur
    {
        $entity = Utilisateur::fromDatabaseRow($row);
        $entity->setRole($this->normalizeRole($entity->getRole()));
        return $entity;
    }

    private function normalizeUserRow(array $row): array
    {
        $entity = $this->buildUtilisateurFromRow($row);

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'nom' => $entity->getNom(),
            'prenom' => $entity->getPrenom(),
            'email' => $entity->getEmail(),
            'telephone' => $entity->getTelephone(),
            'role' => $entity->getRole(),
            'statut_compte' => (string) ($row['statut_compte'] ?? 'actif'),
            'date_creation' => (string) ($row['date_creation'] ?? '')
        ];
    }

    private function validatePayload(array $payload, bool $isUpdate): array
    {
        $nom = trim((string) ($payload['nom'] ?? ''));
        $prenom = trim((string) ($payload['prenom'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $telephone = preg_replace('/\s+/', '', (string) ($payload['telephone'] ?? ''));
        $role = $this->normalizeRole((string) ($payload['role'] ?? ''));
        $statutCompte = strtolower(trim((string) ($payload['statut_compte'] ?? 'actif')));

        $errors = [];

        if (!preg_match('/^[\p{L}\s\'-]{2,60}$/u', $nom)) {
            $errors[] = 'Le nom doit contenir 2 a 60 caracteres alphabetiques.';
        }

        if (!preg_match('/^[\p{L}\s\'-]{2,60}$/u', $prenom)) {
            $errors[] = 'Le prenom doit contenir 2 a 60 caracteres alphabetiques.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        } elseif (!$this->emailDomainExists($email)) {
            $errors[] = "Le domaine de l'adresse e-mail n'existe pas ou ne recoit pas d'e-mails.";
        } elseif (!$isUpdate && $role === 'client') {
            $mailboxCheck = $this->emailMailboxExists($email);
            if ($mailboxCheck === false) {
                $errors[] = "Cette adresse e-mail semble inexistante. Verifiez l'orthographe puis reessayez.";
            }
        }

        if (!preg_match('/^\+?[0-9]{8,15}$/', $telephone)) {
            $errors[] = 'Le telephone doit contenir entre 8 et 15 chiffres.';
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $errors[] = 'Le role doit etre admin, agent ou client.';
        }

        if (!in_array($statutCompte, self::ALLOWED_ACCOUNT_STATUS, true)) {
            $errors[] = 'Le statut du compte doit etre actif, bloque ou en_pause.';
        }

        if (!$isUpdate || $password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Le mot de passe doit contenir au moins 6 caracteres.';
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'password' => $password,
            'telephone' => $telephone,
            'role' => $role,
            'statut_compte' => $statutCompte
        ];
    }

    private function emailDomainExists(string $email): bool
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = strtolower(trim((string) $parts[1]));
        if ($domain === '') {
            return false;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = @idn_to_ascii($domain, IDNA_DEFAULT, defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0);
            if (is_string($ascii) && $ascii !== '') {
                $domain = $ascii;
            }
        }

        if (function_exists('checkdnsrr')) {
            if (@checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')) {
                return true;
            }
        }

        if (function_exists('dns_get_record')) {
            $mx = @dns_get_record($domain, DNS_MX);
            $a = @dns_get_record($domain, DNS_A);
            $aaaa = @dns_get_record($domain, DNS_AAAA);
            if (!empty($mx) || !empty($a) || !empty($aaaa)) {
                return true;
            }
        }

        $hosts = @gethostbynamel($domain);
        return is_array($hosts) && count($hosts) > 0;
    }

    private function emailMailboxExists(string $email): ?bool
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = strtolower(trim((string) $parts[1]));
        if ($domain === '') {
            return false;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = @idn_to_ascii($domain, IDNA_DEFAULT, defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0);
            if (is_string($ascii) && $ascii !== '') {
                $domain = $ascii;
            }
        }

        $mxHosts = [];
        $mxWeights = [];

        if (function_exists('getmxrr')) {
            @getmxrr($domain, $mxHosts, $mxWeights);
        }

        if (count($mxHosts) > 1 && count($mxWeights) === count($mxHosts)) {
            array_multisort($mxWeights, SORT_ASC, $mxHosts);
        }

        if ($mxHosts === []) {
            $mxHosts = [$domain];
        }

        foreach (array_slice($mxHosts, 0, 2) as $host) {
            $result = $this->probeMailboxOnHost((string) $host, $email);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    private function probeMailboxOnHost(string $host, string $email): ?bool
    {
        $host = trim($host);
        if ($host === '') {
            return null;
        }

        $errorNo = 0;
        $errorString = '';
        $socket = @stream_socket_client('tcp://' . $host . ':25', $errorNo, $errorString, 4);
        if (!is_resource($socket)) {
            return null;
        }

        stream_set_timeout($socket, 4);

        $banner = $this->smtpReadLine($socket);
        if ($banner === null || $this->smtpStatusCode($banner) !== 220) {
            fclose($socket);
            return null;
        }

        $hostname = parse_url((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
        if (!$this->smtpWriteLine($socket, 'EHLO ' . $hostname)) {
            fclose($socket);
            return null;
        }

        $ehlo = $this->smtpReadResponse($socket);
        $ehloCode = $ehlo !== null ? $this->smtpStatusCode($ehlo) : null;
        if ($ehloCode !== 250) {
            if (!$this->smtpWriteLine($socket, 'HELO ' . $hostname)) {
                fclose($socket);
                return null;
            }
            $helo = $this->smtpReadLine($socket);
            if ($helo === null || $this->smtpStatusCode($helo) !== 250) {
                fclose($socket);
                return null;
            }
        }

        if (!$this->smtpWriteLine($socket, 'MAIL FROM:<noreply@secondvoice.local>')) {
            fclose($socket);
            return null;
        }

        $mailFromResponse = $this->smtpReadLine($socket);
        if ($mailFromResponse === null || $this->smtpStatusCode($mailFromResponse) !== 250) {
            $this->smtpWriteLine($socket, 'QUIT');
            fclose($socket);
            return null;
        }

        if (!$this->smtpWriteLine($socket, 'RCPT TO:<' . $email . '>')) {
            $this->smtpWriteLine($socket, 'QUIT');
            fclose($socket);
            return null;
        }

        $rcptResponse = $this->smtpReadLine($socket);
        $this->smtpWriteLine($socket, 'QUIT');
        $this->smtpReadLine($socket);
        fclose($socket);

        if ($rcptResponse === null) {
            return null;
        }

        $rcptCode = $this->smtpStatusCode($rcptResponse);
        if ($rcptCode === null) {
            return null;
        }

        if (in_array($rcptCode, [250, 251, 252], true)) {
            return true;
        }

        if (in_array($rcptCode, [550, 551, 552, 553, 554], true)) {
            return false;
        }

        return null;
    }

    private function smtpReadLine($socket): ?string
    {
        $line = @fgets($socket, 1024);
        if (!is_string($line)) {
            return null;
        }

        $line = trim($line);
        return $line === '' ? null : $line;
    }

    private function smtpReadResponse($socket): ?string
    {
        $full = '';
        $attempts = 0;

        while ($attempts < 12) {
            $line = $this->smtpReadLine($socket);
            if ($line === null) {
                return $full !== '' ? $full : null;
            }

            $full = $line;
            $attempts++;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                return $full;
            }
        }

        return $full !== '' ? $full : null;
    }

    private function smtpWriteLine($socket, string $line): bool
    {
        return @fwrite($socket, $line . "\r\n") !== false;
    }

    private function smtpStatusCode(string $response): ?int
    {
        if (!preg_match('/^(\d{3})/', $response, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function ensureAuthSchema(): void
    {
        if (self::$authSchemaChecked) {
            return;
        }

        if (!$this->tableExists('utilisateur')) {
            throw new RuntimeException("Table 'utilisateur' introuvable.");
        }

        $emailVerifiedColumnAdded = false;
        if (!$this->columnExists('utilisateur', 'email_verifie')) {
            $this->conn->exec('ALTER TABLE utilisateur ADD COLUMN email_verifie TINYINT(1) NOT NULL DEFAULT 0');
            $emailVerifiedColumnAdded = true;
        }

        $this->conn->exec(
            'CREATE TABLE IF NOT EXISTS email_verification_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_evt_user (user_id),
                INDEX idx_evt_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $this->conn->exec(
            'CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_prt_user (user_id),
                INDEX idx_prt_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        if ($emailVerifiedColumnAdded) {
            $this->conn->exec('UPDATE utilisateur SET email_verifie = 1');
        } else {
            $this->conn->exec("UPDATE utilisateur SET email_verifie = 1 WHERE role IN ('admin', 'agent') AND email_verifie = 0");
        }

        self::$authSchemaChecked = true;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $stmt->execute([':table' => $table]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column
        ]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function createToken(string $table, int $userId, int $expiryHours): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->conn->prepare("UPDATE {$table} SET used_at = :now WHERE user_id = :user_id AND used_at IS NULL");
        $stmt->execute([
            ':now' => $now,
            ':user_id' => $userId
        ]);

        $stmt = $this->conn->prepare(
            "INSERT INTO {$table} (user_id, token_hash, expires_at, used_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, NULL, :created_at)"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':created_at' => $now
        ]);

        return $token;
    }

    private function getActiveTokenRow(string $table, string $tokenHash): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, user_id, expires_at, used_at FROM {$table} WHERE token_hash = :token_hash LIMIT 1"
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        if (!empty($row['used_at'])) {
            return null;
        }

        $expiresAt = strtotime((string) $row['expires_at']);
        if ($expiresAt === false || $expiresAt < time()) {
            return null;
        }

        return $row;
    }

    private function markTokenAsUsed(string $table, string $tokenHash): void
    {
        $stmt = $this->conn->prepare("UPDATE {$table} SET used_at = :used_at WHERE token_hash = :token_hash");
        $stmt->execute([
            ':used_at' => date('Y-m-d H:i:s'),
            ':token_hash' => $tokenHash
        ]);
    }

    private function normalizeTokenHash(string $token): string
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new InvalidArgumentException('Token invalide.');
        }
        return hash('sha256', $token);
    }

    private function setEmailVerified(int $userId, bool $verified): void
    {
        $stmt = $this->conn->prepare('UPDATE utilisateur SET email_verifie = :verified WHERE id = :id');
        $stmt->execute([
            ':verified' => $verified ? 1 : 0,
            ':id' => $userId
        ]);
    }

    private function encodeUrlForEmail(string $url): string
    {
        // Keep link human readable, but encode spaces so mail clients do not truncate at folder names.
        return str_replace(' ', '%20', trim($url));
    }

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        $provider = class_exists('Config') && method_exists('Config', 'getMailProvider')
            ? strtolower((string) Config::getMailProvider())
            : 'brevo';

        $sent = false;
        $error = '';

        if ($provider === 'brevo' || $provider === 'auto') {
            $brevoConfig = class_exists('Config') && method_exists('Config', 'getBrevoConfig')
                ? Config::getBrevoConfig()
                : [];
            $brevoMailer = new BrevoMailer($brevoConfig);
            $sent = $brevoMailer->send($to, $subject, $body);
            $error = $brevoMailer->getLastError();
            if ($sent) {
                $this->logEmail($to, $subject, $body, true, '', 'brevo');
                return true;
            }
        }

        if ($provider === 'smtp' || $provider === 'auto') {
            $smtpConfig = class_exists('Config') && method_exists('Config', 'getSmtpConfig')
                ? Config::getSmtpConfig()
                : [];
            $smtpMailer = new SmtpMailer($smtpConfig);
            $sent = $smtpMailer->send($to, $subject, $body);
            $smtpError = $smtpMailer->getLastError();
            if ($sent) {
                $this->logEmail($to, $subject, $body, true, '', 'smtp');
                return true;
            }
            $error = $error !== '' ? ($error . ' | SMTP: ' . $smtpError) : $smtpError;
        }

        $this->logEmail($to, $subject, $body, false, $error, $provider);
        return false;
    }

    private function logEmail(string $to, string $subject, string $body, bool $sent, string $error = '', string $provider = ''): void
    {
        $dir = __DIR__ . '/../storage/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $logPath = $dir . '/outbox.log';
        $content = "----\n";
        $content .= 'date: ' . date('Y-m-d H:i:s') . "\n";
        $content .= 'to: ' . $to . "\n";
        $content .= 'subject: ' . $subject . "\n";
        if ($provider !== '') {
            $content .= 'provider: ' . $provider . "\n";
        }
        $content .= 'mail_sent: ' . ($sent ? 'yes' : 'no') . "\n";
        if (!$sent && $error !== '') {
            $content .= 'mail_error: ' . $error . "\n";
        }
        $content .= "body:\n" . $body . "\n";
        $content .= "----\n\n";
        @file_put_contents($logPath, $content, FILE_APPEND);
    }
}
?>
