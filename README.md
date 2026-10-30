# Beog-Zaadba — Livraison du 21 octobre 2026

## Version de présentation
34 pages FR/EN + accueil racine + 404. Dix gabarits : accueil, école, liste des projets, fiche projet, partenaires, transparence, liste d’actualités, article, contact, page institutionnelle. Quatre projets déclinés dans les deux langues. Deux notes éditoriales en français, également accessibles depuis la navigation anglaise conformément au cahier des charges.

Le site publié est statique, rapide et portable. Il ne constitue pas la réception complète du lot A administrable WordPress. Le lot B paiements est conditionnel et n’est pas activé. Aucun faux reçu, financement, témoignage ou partenaire n’est affiché.

## Utilisation
- Lancer `node server.mjs`, ouvrir http://127.0.0.1:4173.
- Modifier `content.mjs` pour les projets, le budget et les notes ; `build.mjs` pour les autres textes et gabarits.
- Modifier `dist/styles.css` et `dist/app.js` pour les styles et interactions.
- Exécuter `node build.mjs`, puis `node check.mjs`.
- Le répertoire `dist` est déployable sur un hébergement statique. Aucun paquet npm à installer.
- Le budget source utilise des montants XOF entiers. Le total est 71 168 700 FCFA ; base 62 878 000 et réserves 8 290 700.
- Les formulaires préparent uniquement du texte local : ils ne transmettent ni ne stockent les données. Aucun service de courrier n’est configuré.
- La vidéo est chargée après un clic, avec le son d’origine et des commandes de volume. Elle ne démarre pas à l’ouverture de la page.

## WordPress — socle du lot A (extension 2.0)
Le dossier `wordpress` contient le thème `beog-zaadba` et l’extension `beog-contenus`, séparée du thème. `node build.mjs` régénère les copies du thème et le fichier d’import à partir du site statique.

**Installation (PHP 8.1 ou plus récent, WordPress 6.6 ou plus récent)**
1. Copier `wordpress/beog-zaadba` dans `wp-content/themes` et `wordpress/beog-contenus` dans `wp-content/plugins`, puis activer l’extension et le thème.
2. Réglages > Permaliens : choisir « Titre de la publication » (adresses /fr/… et /en/…).
3. Outils > Import Beog-Zaadba (ou `wp beog import`) : les 34 contenus FR/EN sont créés en brouillon. Les fiches projet reçoivent leur budget daté et l’état « En préparation ».
4. Relire, puis publier depuis l’administration. Réglages > Lecture : page d’accueil statique = l’accueil français.
5. Réglages > Général : renseigner « Courriel des demandes Beog-Zaadba ». Vérifier que le serveur envoie des courriels (extension SMTP si besoin).
6. Pièces privées : définir `BEOG_PRIVATE_DIR` dans `wp-config.php` vers un dossier hors de la racine web. À défaut, `wp-content/beog-private/` est protégé par `.htaccess` (Apache) ; sous Nginx, ajouter `location ^~ /wp-content/beog-private/ { deny all; }`.
7. Le thème garde `noindex` jusqu’à la validation de la version en ligne.

**Ce que gère l’école**
- *Fiches projet* : état public parmi les six états du cahier (En préparation, Ouvert au soutien, Financement atteint, En réalisation, Terminé, Suspendu), chacun avec son message ; budget daté et historisé ; ressources sécurisées (vide = non renseigné, jamais zéro) ; solde calculé ; jauge affichée seulement si l’objectif et sa règle sont validés. Les chiffres et l’état saisis sur une langue sont recopiés sur l’autre ; les textes (responsable, lieu, calendrier, bénéficiaires) restent propres à chaque langue. Les pastilles d’état de l’accueil et de la liste des projets suivent automatiquement.
- *Langues* : chaque contenu porte son adresse fr/… ou en/… ; un bouton crée la version de l’autre langue en brouillon.
- *Archivage* : action « Archiver » dans les listes ; un contenu archivé quitte le site sans être supprimé.
- *Suivi des projets* : une actualité ou un rapport rattaché à un projet apparaît dans « Suivi du projet » de la fiche, dans sa langue.
- *Demandes* : les formulaires de contact et de partenariat enregistrent chaque demande en privé avant d’envoyer la notification. Contrôles : jeton anti-falsification, champ piège, 5 demandes au plus par connexion sur 10 minutes, validation serveur. Si le courriel échoue, la demande reste enregistrée et une alerte s’affiche dans l’administration. Suivi par état, responsable et prochaine action ; export CSV protégé contre les formules.
- *Galerie photos* : albums avec titre, date, courte description (FR, EN facultatif) et lot de photos ou de vidéos MP4, dans l’ordre choisi par glisser-déposer. Tous les rôles créent des albums et les soumettent ; la direction les publie. Un avertissement sur les autorisations d’image s’affiche sur les écrans d’album et de médiathèque, et chaque enregistrement exige de cocher la confirmation : sans elle, l’album reste en brouillon. Les légendes et textes alternatifs anglais se saisissent dans la médiathèque. Pages publiques : /fr/galerie/, /en/galerie/ et /fr|en/galerie/<album>/, avec visionneuse au clavier.
- *Pièces privées* : devis, conventions et justificatifs rangés hors de la médiathèque, sous nom aléatoire, téléchargeables seulement après vérification des droits ; PDF, images, Word, Excel, CSV, 10 Mo au plus.

**Rôles**
| Rôle | Peut |
|---|---|
| Rédaction Beog-Zaadba | Préparer et modifier des brouillons, les soumettre à relecture ; créer des albums photos. Ne publie pas, ne change ni l’état ni les chiffres. |
| Direction Beog-Zaadba | Valider et publier (y compris les albums), fixer état et chiffres des projets, traiter et exporter les demandes, gérer les pièces privées. |
| Finance Beog-Zaadba | Consulter et télécharger les pièces privées ; exports financiers (lot B) ; créer des albums photos. |
| Partenariats Beog-Zaadba | Traiter et exporter les demandes de contact et de partenariat ; créer des albums photos. |
| Administrateur | Maintenance technique ; toutes les capacités. |

**Commandes WP-CLI** : `wp beog import`, `wp beog publish` (refusée en production), `wp beog export-inquiries fichier.csv`.

**Testé** sur WordPress 7.1.4, PHP 8.3 : import et publication, routes FR/EN et 404, aperçu des brouillons réservé aux éditeurs, droits de chaque rôle (écrans et téléchargements), saisie des chiffres limitée à la direction, recopie FR→EN et historique du budget, jauge, suivi des projets, traduction, archivage, formulaire en navigateur (FR/EN), refus pour jeton invalide, champ piège, limitation, champs invalides, alerte de courriel en panne, export CSV neutralisant une formule, refus d’un fichier `.php`.

**Hors de ce socle** : paiements (lot B), dont la double validation d’un changement de bénéficiaire ; authentification renforcée (à activer avec une extension à deux facteurs, par exemple « Two Factor ») ; sauvegardes quotidiennes et restauration (hébergement) ; relecture des phrases d’accueil qui mentionnent l’état d’une collecte lorsqu’un projet change d’état.

## Provenance et décisions éditoriales
- Cahier des charges du site web — Beog-Zaadba, version 1 du 20 octobre 2026.
- Budget détaillé des besoins — Beog-Zaadba, calcul au 1er octobre 2026.
- Documents originaux consultés, laissés inchangés.
- Logos : trois PNG corrigés fournis dans le dossier Logos ; copies WebP sans modification du texte, des couleurs ou des proportions. Porte/fenêtre vertes.
- Photographies : 25 images inspectées. Portraits d’enfants et photos de documents administratifs non publiés dans cette présentation.
- Vidéos : 7 fichiers inspectés. Clip utilisé : WhatsApp Video 2026-10-14 at 07.24.00.mp4, 00:08–00:20, adulte seul, sans attribution d’identité/fonction.
- Vidéo livrée : 12 secondes, 404 × 720, H.264 + AAC, 2 721 536 octets, piste sonore d’origine rétablie ; image extraite à 00:12, 478 × 850, 109 988 octets.
- Les illustrations des projets sont des pictogrammes décoratifs, pas des preuves de travaux réalisés.
- Galerie (30 octobre 2026) : 37 photos et 9 vidéos examinées. Retenus : 4 photos et un extrait vidéo de 22 secondes, tous du 29 octobre 2026 (bâtiment, cour, descente des couleurs), où aucun enfant n’est reconnaissable ; deux photos recadrées pour écarter des visages proches. Images converties en WebP, métadonnées retirées. Second album « Remise des kits » (14 octobre 2026, 7 photos) : enfants reconnaissables, publié après validation des autorisations d’image par l’école le 30 octobre 2026. Non publiés : les autres photos de classe (autorisation à obtenir) et les quasi-doublons de la remise, les deux témoignages filmés (accord des personnes à obtenir), deux documents administratifs et un doublon.
- Effectifs : 98 élèves en 2025–2026 (41 filles, 57 garçons), confirmés par la direction le 30 octobre 2026, affichés avec leur année. Effectif 2026–2027 non publié.
- Tarif ancien, contact, compte de paiement, entité légale et devis non confirmés : aucun renseignement inventé.
- Les estimations ne sont ni des objectifs de collecte, ni des devis TTC. Les ressources sécurisées sont inconnues, pas nulles.
- Aucun traceur ajouté ; aucune police externe ; aucun identifiant secret dans le dépôt.

## Vérification
- Build et contrôle syntaxique JavaScript réussis.
- 37 documents HTML, 1 010 références locales contrôlées, tous les fichiers liés présents.
- Total des 11 groupes et ventilation investissements/charges annuelles vérifiés.
- Poids initial calculé : 215 300 octets, hors vidéo à la demande.
- Edge : affichages ordinateur et mobiles 320/390 px vérifiés ; filtres, menus, formulaires et ouverture/fermeture de la vidéo testés ; aucun débordement horizontal ni message d’erreur JavaScript observé.
- Les mesures Web Vitals en trafic réel, audit WCAG complet, Safari/Firefox, formation de deux personnes et test utilisateurs externes ne sont pas réalisés. Pas de promesse de certification.
- La publication est validée séparément par l’état de déploiement de l’hébergeur.

## Réversibilité
Les contenus structurés, gabarits, styles, scripts, médias web et socle WordPress sont fournis dans le même dépôt. Les originaux personnels et documents administratifs ne sont pas rendus publics.

## Mise à jour animations et son — 21 octobre 2026
Quatre illustrations vectorielles animées (école, livre, solidarité, jardin), apparitions au défilement et interactions légères. Les séquences décoratives durent au maximum 4,2 secondes, se déclenchent à leur entrée dans la vue et respectent la préférence de réduction du mouvement. L’extrait vidéo conserve les images 00:08–00:20 et retrouve la piste sonore correspondante du fichier d’origine.
