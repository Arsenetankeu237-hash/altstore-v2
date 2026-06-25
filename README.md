# 🏪 ALT STORE ERP v2

ERP de gestion commerciale **multi-boutiques** sécurisé, développé en PHP procédural + MySQL.
Refonte complète et propre de l'ancien projet, corrigée des vulnérabilités critiques et enrichie d'une architecture native multi-boutiques.

---

## ✨ Nouveautés par rapport à la v1

| Domaine | Ancien projet (v1) | **v2** |
|---|---|---|
| **Architecture** | Tout dans la racine, doublons, dossier `charte/` mélangé | Structure claire (`config/`, `api/`, `views/`, `assets/`, `database/`) |
| **Sécurité** | Clé API IA exposée dans le JS, IDOR (`?id=N`), pas de CSRF | Proxy IA serveur, garde de session, CSRF sur tout, credentials en `.env` |
| **Multi-boutiques** | ❌ inexistant (tout rattaché à `user_id`) | ✅ Natif : un propriétaire possède N boutiques isolées |
| **Rôles & permissions** | Système `role` séparé et incohérent | Matrice centralisée par boutique (7 rôles, permissions granulaires) |
| **Caisses** | Créées à la main, non liées à une boutique | **Auto-créées par boutique**, avec mouvements, transferts, soldes calculés |
| **Code** | HTML+CSS+JS+SQL mélangés dans chaque fichier | Layout commun, helpers, fonctions réutilisables |

---

## 🚀 Installation (3 étapes)

### 1. Préparer la base de données
Créez une base MySQL (ex: `altstore_v2`) en UTF-8 (`utf8mb4`).

### 2. Configurer le `.env`
Copiez `.env.example` en `.env` et renseignez vos identifiants :
```bash
DB_HOST=127.0.0.1
DB_NAME=altstore_v2
DB_USER=root
DB_PASS=
APP_KEY=generer_une_chaine_aleatoire_de_64_caracteres
OPENROUTER_API_KEY=votre_cle_ici   # optionnel, pour l'IA
```

### 3. Lancer l'installation automatique
Ouvrez dans le navigateur :
```
http://localhost/altstore-v2/database/install.php
```
Ce script :
- ✅ crée toutes les tables (multi-boutiques) ;
- ✅ crée un compte propriétaire de démo (**admin@altstore.ci / admin123**) ;
- ✅ crée **2 boutiques de démo** avec leurs caisses.

> ⚠️ **Supprimez `database/install.php` après installation** (en production).

Connectez-vous ensuite sur `login.php`.

---

## 🏗️ Architecture

```
altstore-v2/
├── config/                  SOCLE (à ne pas modifier)
│   ├── config.php          .env + constantes + session sécurisée
│   ├── db.php              Connexion PDO singleton
│   ├── bootstrap.php       Charge tout le socle en 1 require
│   ├── auth.php            Auth + contexte multi-boutiques ⭐
│   ├── permissions.php     Matrice rôles/permissions
│   ├── csrf.php            Protection CSRF
│   └── helpers.php         money(), e(), fetch_one(), solde_caisse()...
│
├── database/
│   ├── schema.sql          Schéma complet multi-boutiques
│   └── install.php         Installation 1-clic
│
├── api/                     Endpoints (JSON / SSE)
│   ├── switch_boutique.php  Change de boutique active
│   └── ia_proxy.php         Proxy IA (clé cachée serveur)
│
├── views/layouts/
│   ├── header.php          Sidebar + sélecteur de boutique + nav filtrée
│   └── footer.php
│
├── assets/
│   ├── app.css             Design system (ember/gold)
│   └── app.js              Switch boutique, CSRF pour fetch
│
├── index.php               Routeur (login/dashboard)
├── login.php / register.php / logout.php
├── dashboard.php           KPIs filtrés par boutique
├── boutiques.php           Gestion multi-boutiques ⭐
├── personnel.php           Staff + rôles par boutique ⭐
├── caisses.php             Caisses dynamiques + mouvements ⭐
├── ventes.php              Point de vente (POS)
├── articles.php            Catalogue + stock
├── clients.php             Fichier clients
├── fournisseurs.php        Carnet fournisseurs
├── factures.php            Factures émises
├── rapports.php            Analyses & statistiques
└── ia.php                  Assistant IA (sécurisé)
```

---

## 🧠 Concepts clés de l'architecture

### 1. Le concept de « boutique » (tenancy)
Dans un ERP multi-boutiques, **chaque donnée métier appartient à une boutique**. C'est la clé d'isolation :
```
articles.boutique_id   ventes.boutique_id   caisses.boutique_id   ...
```
Ainsi, un propriétaire qui possède la Boutique A et la Boutique B voit des **stocks, ventes et caisses totalement séparés**. Le changement de contexte se fait via le sélecteur de la sidebar.

### 2. Les rôles (par boutique)
Un même utilisateur peut être **caissier dans la Boutique A** et **directeur dans la Boutique B** :
- Table `utilisateurs_boutique` (utilisateur_id, boutique_id, role_boutique)
- 7 rôles : `proprietaire`, `directeur`, `comptable`, `commercial`, `caissier`, `gestionnaire_stock`, `employe`
- Permissions granulaires : `articles.create`, `caisse.encaisser`, `factures.export`...
- Le propriétaire a **toujours toutes les permissions** dans ses boutiques.

### 3. Les caisses (liées à la boutique)
Quand une boutique est créée → sa **caisse principale est générée automatiquement**. Vous pouvez ensuite :
- Créer d'autres caisses (avec solde d'ouverture) ;
- Encaisser / décaisser ;
- Transférer entre caisses ;
- Suivre chaque mouvement (qui, quand, pourquoi, mode de paiement).

### 4. La sécurité par défaut
Chaque page protégée commence par **une seule ligne** :
```php
require_once __DIR__ . '/config/bootstrap.php';
require_permission('ventes.view');   // ou require_login() / require_boutique()
```
Cela garantit :
- ✅ l'utilisateur est connecté ;
- ✅ une boutique est active ;
- ✅ l'utilisateur a la permission requise dans cette boutique.

---

## 🔐 Sécurité — ce qui a été corrigé

| Vulnérabilité v1 | Correction v2 |
|---|---|
| 🔴 Clé API OpenRouter dans le JS public | **Proxy `api/ia_proxy.php`** : la clé reste serveur |
| 🔴 IDOR (`tableau-de-bord.php?id=N` → accès à tous) | Garde de session `require_login()` + boutique tirée de `$_SESSION` |
| 🔴 Credentials BDD en clair dans `db.php` | Variables `.env` (non versionnées) |
| 🟠 `display_errors` activé en prod | Désactivé quand `APP_ENV=production` |
| 🟠 Messages d'erreur PDO renvoyés au client | Journalisation (`error_log`) + message générique |
| 🟡 Pas de CSRF | `csrf_token()` / `csrf_verify()` sur tous les formulaires |
| 🟠 `remember_token` fantôme | Supprimé (à réimplémenter proprement si besoin) |

---

## 👤 Comptes & rôles de test

Après `install.php`, le compte suivant est prêt :
- **Email** : `admin@altstore.ci`
- **Mot de passe** : `admin123`
- **Rôle** : propriétaire (2 boutiques de démo : « ALT STORE Principal » et « ALT STORE Annexe »)

Pour tester les permissions, créez un employé via `personnel.php` (ex: un caissier) et connectez-vous avec son compte : il ne verra que les modules autorisés.

---

## 🛠️ Guide de migration depuis la v1

La v2 ne supprime rien de l'ancien projet (resté dans `Nouveau dossier/`).
Pour reprendre vos **données existantes**, voici la logique de mapping :

### Étape 1 — Récupérer les utilisateurs
Vos comptes `utilisateurs` (v1) sont compatibles : la table v2 a les mêmes champs cœur (`id`, `email`, `mot_de_passe`, `prenom`, `nom`...). Vous pouvez exporter/importer directement cette table.

### Étape 2 — Créer les boutiques manquantes
En v1, chaque utilisateur était sa propre « boutique ». Pour migrer :
- Pour chaque utilisateur v1, créez une ligne dans `boutiques` :
  ```sql
  INSERT INTO boutiques (proprietaire_id, nom, slug, code, couleur, ville, devise, tva_defaut, statut)
  SELECT id, COALESCE(nom_entreprise, CONCAT(prenom,' ',nom)), LOWER(id), CONCAT('ALT-', LPAD(id,3,'0')),
         '#F9A825', 'Abidjan', 'XOF', 18.00, 'active'
  FROM utilisateurs;
  ```

### Étape 3 — Convertir `user_id` en `boutique_id`
Les données métier v1 utilisent `user_id`. Il faut les rattacher à la boutique correspondante :
```sql
-- Articles
INSERT INTO articles_v2 (boutique_id, nom_article, reference, code_barre, marque, quantite_stock, stock_min,
                         prix_achat, prix_vente, tva, unite_mesure, description, image_url, statut)
SELECT b.id, nom_article, reference, code_barre, marque, quantite_stock, stock_min,
       prix_achat, prix_vente, tva, unite_mesure, description, image_url, statut
FROM articles a JOIN boutiques b ON b.proprietaire_id = CAST(a.user_id AS UNSIGNED);
```
Même logique pour `clients`, `fournisseurs`, `ventes` (table `sales` v1 → `ventes` v2), etc.

### Étape 4 — Vérifier
Connectez-vous avec un compte migré → créez/activez la boutique → vos données apparaissent.

> 💡 **Conseil** : testez d'abord la migration sur une **copie** de votre base de production.

---

## ❓ FAQ

**Q : Mon ancien projet va-t-il être supprimé ?**
R : Non. La v2 est dans `altstore-v2/`, à côté. Les deux coexistent.

**Q : Puis-je garder l'ancien `db.php` ?**
R : Déconseillé : il contient vos credentials en clair. Utilisez le `.env`.

**Q : L'IA ne marche pas ?**
R : Renseignez `OPENROUTER_API_KEY` dans `.env`. Sans clé, le proxy renvoie 503 proprement.

**Q : Comment ajouter une permission ?**
R : Éditez `config/permissions.php` (`PERMISSIONS_CATALOG` + `ROLE_PERMISSIONS`).

**Q : Comment activer HTTPS pour les cookies sécurisés ?**
R : Le code détecte `$_SERVER['HTTPS']`. En prod, forcez HTTPS côté serveur web.
