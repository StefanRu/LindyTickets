# 🎟️ Lindy Tickets v2 — Multi-événements

## Nouveautés v2
- **Multi-événements** : gérez plusieurs soirées simultanément, chacune avec sa propre liste
- **Ajout à la volée** : ajoutez un invité + générez son ticket en 2 clics, depuis le scanner ou l'admin
- **Reset individuel** : remettez à zéro le check-in d'un seul ticket (utile si erreur de scan)
- **Ticket PDF à la volée** : générez un ticket imprimable pour n'importe quel invité via `api.php?action=ticket_pdf&code=XXX`

## 📁 Fichiers
```
├── .htaccess       ← Sécurité + HTTPS
├── config.php      ← Configuration BDD (à modifier !)
├── schema.sql      ← Schéma SQL
├── install.php     ← Installation (supprimer après)
├── api.php         ← Backend REST
├── index.html      ← Scanner (page principale)
├── admin.html      ← Administration
└── tickets.html    ← Vue tous les tickets (imprimable)
```

## 🚀 Installation
1. Créez une base MariaDB sur votre hébergeur
2. Éditez `config.php` avec vos identifiants + mot de passe admin
3. Uploadez tous les fichiers via FTP
4. Allez sur `https://tickets.votresite.com/install.php`
5. Supprimez `install.php`
6. Allez sur `/admin.html` → créez un événement → importez votre Excel

## 📱 Jour J
- Ouvrez `https://tickets.votresite.com` sur le téléphone
- Sélectionnez l'événement (si plusieurs)
- Scannez les QR codes ou cherchez par nom
- Onglet ➕ pour ajouter un invité de dernière minute

## 🔗 URLs
| Page | URL |
|------|-----|
| Scanner | https://tickets.votresite.com/ |
| Admin | https://tickets.votresite.com/admin.html |
| Tickets | https://tickets.votresite.com/tickets.html?event_id=X |
| Ticket unique | https://tickets.votresite.com/api.php?action=ticket_pdf&code=XXX |
