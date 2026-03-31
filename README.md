# 🎟️ Lindy Tickets — Système de billetterie avec QR codes

Système de gestion d'entrées pour événements avec scan QR, hébergé sur serveur web PHP + MariaDB.

## 📁 Structure des fichiers

```
votresite
├── .htaccess        ← Sécurité + HTTPS
├── config.php       ← Configuration BDD (à modifier !)
├── schema.sql       ← Schéma SQL
├── install.php      ← Script d'installation (à supprimer après)
├── api.php          ← API REST (backend)
├── index.html       ← Scanner (page principale)
├── admin.html       ← Panneau d'administration
└── tickets.html     ← Générateur de tickets imprimables
```

## 🚀 Installation

### 1. Créer la base de données
Dans votre panneau hébergeur, créez une base de données MariaDB et notez :
- Nom de la BDD
- Utilisateur
- Mot de passe

### 2. Configurer
Éditez `config.php` avec vos identifiants :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'votre_base');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
define('ADMIN_PASSWORD', 'un-mot-de-passe-solide');
```

### 3. Uploader les fichiers
Uploadez tous les fichiers via FTP dans le dossier de `votresite`.

### 4. Installer
Allez sur `https://tickets.votresite.com/install.php` pour créer les tables.

**⚠️ Supprimez `install.php` après installation !**

### 5. Importer les invités
1. Allez sur `https://tickets.votresite.com/admin.html`
2. Connectez-vous avec le mot de passe admin
3. Uploadez votre fichier Excel (Nom, Prénom, Nb Tickets)
4. Ou collez directement du JSON

### 6. Générer les tickets
Allez sur `https://tickets.votresite.com/tickets.html` pour voir/imprimer les tickets avec QR codes.

## 📱 Le jour de l'événement

1. Ouvrez `https://tickets.votresite.com` sur le téléphone Android
2. Scannez les QR codes ou cherchez les noms dans l'onglet Liste
3. Les check-ins sont enregistrés en BDD en temps réel

## 🔗 URLs

| Page | URL |
|------|-----|
| Scanner | https://tickets.votresite.com/ |
| Admin | https://tickets.votresite.com/admin.html |
| Tickets | https://tickets.votresite.com/tickets.html |
| API | https://tickets.votresite.com/api.php |
