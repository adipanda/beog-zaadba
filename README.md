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

## Reprise WordPress — socle préparé, non testé en PHP
Le répertoire `wordpress` contient un thème et une extension de contenus séparée. Cette livraison inclut un import initial et un rendu éditable. Elle n’inclut pas encore la recette fonctionnelle d’un serveur WordPress, les formulaires serveurs, les rôles spécifiques finance, les campagnes, les sauvegardes ni les paiements.

1. Sur un environnement WordPress de test compatible PHP 8.1 ou plus récent, installer le dossier `wordpress/beog-zaadba` dans les thèmes et `wordpress/beog-contenus` dans les extensions.
2. Activer l’extension et le thème. Dans Outils > Import Beog-Zaadba, importer les contenus. Ils sont créés en brouillon et les contenus déjà importés ne sont pas écrasés.
3. Examiner les pages et projets. Ils sont éditables dans un bloc HTML dans l’éditeur WordPress ; cet import est destiné à la reprise par le développeur avant formation de l’école.
4. Vérifier les routes /fr/ et /en/, les langues et les liens. Publier après validation. Choisir la page française d’accueil dans Réglages > Lecture. Les noms de fichier et routes restent sans données personnelles.
5. Le thème maintient volontairement noindex. Le retirer seulement après validation institutionnelle et mise en conformité de la version opérationnelle.
6. Configurer les formulaires serveurs avec validation, stockage privé, antispam et notification. Le JavaScript prévoit un point d’intégration BEOG_FORM mais aucun endpoint n’est activé ou livré comme opérationnel.
7. Tester utilisateurs, révisions, publications, traductions, données privées, sauvegarde/restauration, mobile et formulaires avant réception.

## Provenance et décisions éditoriales
- Cahier des charges du site web — Beog-Zaadba, version 1 du 20 octobre 2026.
- Budget détaillé des besoins — Beog-Zaadba, calcul au 1er octobre 2026.
- Documents originaux consultés, laissés inchangés.
- Logos : trois PNG corrigés fournis dans le dossier Logos ; copies WebP sans modification du texte, des couleurs ou des proportions. Porte/fenêtre vertes.
- Photographies : 25 images inspectées. Portraits d’enfants et photos de documents administratifs non publiés dans cette présentation.
- Vidéos : 7 fichiers inspectés. Clip utilisé : WhatsApp Video 2026-10-14 at 07.24.00.mp4, 00:08–00:20, adulte seul, sans attribution d’identité/fonction.
- Vidéo livrée : 12 secondes, 404 × 720, H.264 + AAC, 2 721 536 octets, piste sonore d’origine rétablie ; image extraite à 00:12, 478 × 850, 109 988 octets.
- Les illustrations des projets sont des pictogrammes décoratifs, pas des preuves de travaux réalisés.
- L’effectif 98 pour 2025–2026 est signalé « à confirmer » dans le cahier : pas affiché comme indicateur confirmé. Effectif 2026–2027 non publié.
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
