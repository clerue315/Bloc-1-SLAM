<?php
// Page d'accueil : liste des documents et des favoris + filtre par mots-cles
require_once "includes/fonctions.php";

// Mots-cles tapes dans le filtre (le formulaire est en GET pour pouvoir copier l'url)
$saisie = $_GET["mots-cles"] ?? "";
if (!is_string($saisie)) {
    $saisie = ""; // au cas ou on recoit ?mots-cles[]=...
}
if (!mb_check_encoding($saisie, "UTF-8")) {
    $saisie = mb_convert_encoding($saisie, "UTF-8", "UTF-8");
}
$saisie = trim($saisie);

// On coupe sur les virgules, lire() garde seulement les ressources qui ont tous les mots
$motsSaisis = explode(",", $saisie);
$ressources = lire($motsSaisis);

// Le filtre compte seulement s'il reste un vrai mot (" , ,, " c'est comme rien)
$filtreActif = !empty(nettoyerMotsCles($motsSaisis));

// Message apres un ajout, une modif ou une suppression.
// On affiche uniquement nos phrases, jamais un texte qui vient de l'url.
$messages = [
    "ajout" => "La ressource a été ajoutée.",
    "modification" => "La ressource a été modifiée.",
    "suppression" => "La ressource a été supprimée.",
];

$messageSucces = "";
$code = $_GET["message"] ?? "";
if (is_string($code) && isset($messages[$code])) {
    $messageSucces = $messages[$code];
}

if ($messageSucces != "" && isset($_GET["guillemets"])) {
    $messageSucces .= ' Les guillemets droits (") saisis ont été convertis automatiquement, '
        . "car ce caractère sert de délimiteur dans le fichier de données.";
}

// Compteurs pour la phrase au-dessus de la liste
$nbDocuments = 0;
foreach ($ressources as $r) {
    if (($r["type"] ?? "") === "document") {
        $nbDocuments++;
    }
}
$nbFavoris = count($ressources) - $nbDocuments;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaBox - Mes ressources</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <header class="entete">
        <div class="entete-contenu">
            <a class="logo" href="index.php">
                <span class="logo-icone" aria-hidden="true">🎵</span>
                <span><strong>MediaBox</strong><small>Le partage musical du club</small></span>
            </a>
            <a class="bouton bouton-clair" href="ajouter.php">+ Ajouter une ressource</a>
        </div>
    </header>

    <main>
        <h1 class="visuellement-cache">Mes ressources</h1>

        <?php if ($messageSucces != ""): ?>
            <div class="message message-succes" role="status">
                <p><?= echapper($messageSucces) ?></p>
            </div>
        <?php endif; ?>

        <!-- Filtre -->
        <form class="filtre" method="get" action="index.php" role="search">
            <label for="mots-cles">Filtrer par mots-clés</label>
            <div class="filtre-ligne">
                <input type="search" id="mots-cles" name="mots-cles" value="<?= echapper($saisie) ?>"
                       placeholder="ex : rock, guitare" aria-describedby="aide-filtre">
                <button type="submit" class="bouton">Filtrer</button>
                <a class="bouton bouton-secondaire" href="index.php">Réinitialiser</a>
            </div>
            <p class="aide" id="aide-filtre">
                Séparez les mots-clés par des virgules. Seules les ressources qui ont
                <strong>tous</strong> les mots-clés sont affichées (majuscules et accents ignorés).
            </p>
        </form>

        <?php if (empty($ressources)): ?>

            <div class="aucun-resultat">
                <?php if ($filtreActif): ?>
                    <p>Aucun résultat</p>
                    <p>Aucune ressource ne possède tous les mots-clés « <?= echapper($saisie) ?> ».</p>
                    <p><a class="bouton bouton-secondaire" href="index.php">Afficher toutes les ressources</a></p>
                <?php else: ?>
                    <p>Aucune ressource pour le moment</p>
                    <p><a class="bouton" href="ajouter.php">Ajouter la première ressource</a></p>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <p class="bilan">
                <?= count($ressources) ?> ressource<?= count($ressources) > 1 ? "s" : "" ?>
                (<?= $nbDocuments ?> document<?= $nbDocuments > 1 ? "s" : "" ?>,
                <?= $nbFavoris ?> favori<?= $nbFavoris > 1 ? "s" : "" ?>)
                <?php if ($filtreActif): ?>
                    pour « <?= echapper($saisie) ?> »
                <?php endif; ?>
            </p>

            <ul class="liste-ressources">
                <?php foreach ($ressources as $ressource): ?>
                    <?php
                    $id = $ressource["id"] ?? "";
                    $estDocument = ($ressource["type"] ?? "") === "document";
                    $date = $ressource["date"] ?? "";

                    // mots-cles a afficher (sans les vides)
                    $motsCles = [];
                    foreach (explode(", ", formaterMotsCles($ressource["mots-cles"] ?? "")) as $mot) {
                        if ($mot !== "") {
                            $motsCles[] = $mot;
                        }
                    }

                    if ($estDocument) {
                        // basename() pour eviter un nom du style ../../fichier
                        $nomFichier = basename($ressource["fichier"] ?? "");
                        $cheminFichier = DOSSIER_UPLOADS . $nomFichier;
                        $lienValide = $nomFichier !== "" && is_file($cheminFichier);
                        $lienVoir = DOSSIER_UPLOADS . rawurlencode($nomFichier);
                        $extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
                    } else {
                        // pour un favori on met le lien seulement si l'url est en http ou https
                        $url = $ressource["url"] ?? "";
                        $lienValide = urlValide($url);
                        $lienVoir = $url;
                    }
                    ?>
                    <li>
                        <article class="ressource <?= $estDocument ? "document" : "favori" ?>">
                            <div class="ressource-entete">
                                <?php if ($estDocument): ?>
                                    <span class="badge badge-document"><span aria-hidden="true">📄</span> Document</span>
                                <?php else: ?>
                                    <span class="badge badge-favori"><span aria-hidden="true">🔗</span> Favori</span>
                                <?php endif; ?>

                                <?php if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)): ?>
                                    <time datetime="<?= echapper($date) ?>"><?= echapper(formaterDate($date)) ?></time>
                                <?php endif; ?>
                            </div>

                            <h2><?= echapper($ressource["titre"] ?? "(sans titre)") ?></h2>

                            <!-- apercu : pochette, morceau mp3 ou video -->
                            <?php if ($estDocument && $lienValide): ?>
                                <?php if (in_array($extension, ["jpg", "jpeg", "png", "gif"], true)): ?>
                                    <?php $taille = @getimagesize($cheminFichier); // largeur et hauteur de l'image ?>
                                    <img class="apercu-image" src="<?= echapper($lienVoir) ?>"
                                         alt="Aperçu de « <?= echapper($ressource["titre"] ?? "") ?> »" loading="lazy"
                                         <?= $taille ? 'width="' . $taille[0] . '" height="' . $taille[1] . '"' : "" ?>>
                                <?php elseif ($extension === "mp3"): ?>
                                    <audio class="apercu-media" controls preload="none" src="<?= echapper($lienVoir) ?>"
                                           aria-label="Écouter « <?= echapper($ressource["titre"] ?? "") ?> »">
                                        <a href="<?= echapper($lienVoir) ?>">Télécharger le morceau</a>
                                    </audio>
                                <?php elseif ($extension === "mp4"): ?>
                                    <video class="apercu-media" controls preload="metadata" src="<?= echapper($lienVoir) ?>"
                                           aria-label="Regarder « <?= echapper($ressource["titre"] ?? "") ?> »">
                                        <a href="<?= echapper($lienVoir) ?>">Télécharger la vidéo</a>
                                    </video>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (($ressource["description"] ?? "") !== ""): ?>
                                <p class="description"><?= nl2br(echapper($ressource["description"]), false) ?></p>
                            <?php endif; ?>

                            <!-- type et taille du fichier, ou nom du site -->
                            <?php if ($estDocument && $lienValide): ?>
                                <p class="cible">
                                    Fichier <?= echapper(strtoupper($extension)) ?> - <?= formaterTaille(filesize($cheminFichier)) ?>
                                </p>
                            <?php elseif ($estDocument): ?>
                                <p class="cible alerte">Fichier introuvable sur le serveur</p>
                            <?php elseif ($lienValide): ?>
                                <p class="cible"><?= echapper(parse_url(str_replace("&quot;", '"', $url), PHP_URL_HOST) ?? "") ?></p>
                            <?php else: ?>
                                <p class="cible alerte">Adresse web invalide</p>
                            <?php endif; ?>

                            <!-- un clic sur un mot-cle filtre la liste -->
                            <?php if (!empty($motsCles)): ?>
                                <ul class="mots-cles">
                                    <?php foreach ($motsCles as $motCle): ?>
                                        <li><a href="index.php?mots-cles=<?= urlencode(str_replace("&quot;", '"', $motCle)) ?>"
                                               title="Afficher les ressources ayant ce mot-clé"><?= echapper($motCle) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="sans-mot-cle">Aucun mot-clé</p>
                            <?php endif; ?>

                            <div class="actions">
                                <?php if ($lienValide && $estDocument): ?>
                                    <a class="action-voir" href="<?= echapper($lienVoir) ?>" target="_blank"
                                       title="Ouvrir le fichier dans un nouvel onglet">Voir</a>
                                <?php elseif ($lienValide): ?>
                                    <a class="action-voir" href="<?= echapper($lienVoir) ?>" target="_blank"
                                       rel="noopener noreferrer" title="Ouvrir le site dans un nouvel onglet">Voir</a>
                                <?php endif; ?>
                                <a href="modifier.php?id=<?= urlencode((string) $id) ?>">Modifier</a>
                                <a class="action-supprimer" href="supprimer.php?id=<?= urlencode((string) $id) ?>">Supprimer</a>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php endif; ?>
    </main>

    <footer class="pied">
        <p>MediaBox - prototype de GED pour OrgaPlus (club musical) - données dans data/ressources.txt</p>
    </footer>
</body>
</html>
