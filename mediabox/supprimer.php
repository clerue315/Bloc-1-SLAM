<?php
// Page de confirmation avant de supprimer une ressource.
// Pour un document, la fonction supprimer() efface aussi le fichier dans uploads/.
require_once "includes/fonctions.php";

// on verifie l'id recu dans l'url (?id=...)
$id = lireIdDepuisUrl();
$ressource = null;
if ($id !== null) {
    $ressource = getDocumentById($id);
}

$erreur = "";

// on supprime seulement en POST, jamais avec un simple lien
// (un lien peut etre suivi par erreur)
if ($ressource !== null && $_SERVER["REQUEST_METHOD"] == "POST") {

    if (lireChampPost("confirmation") === "oui") {
        if (supprimer($id)) {
            header("Location: index.php?message=suppression");
            exit;
        }
        $erreur = "La suppression a échoué : la ressource n'existe plus.";
    } else {
        // bouton Annuler : retour a la liste sans rien changer
        header("Location: index.php");
        exit;
    }
}

if ($ressource === null) {
    http_response_code(404);
}

$estDocument = ($ressource["type"] ?? "") === "document";
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaBox - Supprimer une ressource</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <header class="entete">
        <div class="entete-contenu">
            <a class="logo" href="index.php">
                <span class="logo-icone" aria-hidden="true">🎵</span>
                <span><strong>MediaBox</strong><small>Le partage musical du club</small></span>
            </a>
            <a class="bouton bouton-clair" href="index.php">← Retour à la liste</a>
        </div>
    </header>

    <main class="etroit">
        <div class="carte confirmation">
            <?php if ($ressource === null): ?>

                <h1>Ressource introuvable</h1>
                <div class="message message-erreur" role="alert">
                    <p>Aucune ressource ne correspond à cet identifiant : elle a peut-être déjà été supprimée,
                        ou le lien est incorrect.</p>
                </div>
                <p><a class="bouton" href="index.php">Retour à la liste des ressources</a></p>

            <?php else: ?>

                <h1>Supprimer <?= $estDocument ? "ce document" : "ce favori" ?> ?</h1>

                <?php if ($erreur !== ""): ?>
                    <div class="message message-erreur" role="alert">
                        <p><?= echapper($erreur) ?></p>
                    </div>
                <?php endif; ?>

                <p>Vous allez supprimer :</p>
                <p class="titre-ressource"><?= echapper($ressource["titre"] ?? "(sans titre)") ?></p>

                <?php if ($estDocument && !empty($ressource["fichier"])): ?>
                    <p>Le fichier <strong><?= echapper(basename($ressource["fichier"])) ?></strong>
                        sera aussi supprimé du serveur.</p>
                <?php endif; ?>

                <p><strong>Attention, c'est définitif.</strong> Voulez-vous vraiment supprimer ?</p>

                <form method="post" action="supprimer.php?id=<?= urlencode((string) $id) ?>">
                    <div class="boutons-formulaire">
                        <button type="submit" name="confirmation" value="oui" class="bouton bouton-danger">Oui, supprimer</button>
                        <button type="submit" name="confirmation" value="non" class="bouton bouton-secondaire">Annuler</button>
                    </div>
                </form>

            <?php endif; ?>
        </div>
    </main>

    <footer class="pied">
        <p>MediaBox - prototype de GED pour OrgaPlus (club musical) - données dans data/ressources.txt</p>
    </footer>
</body>
</html>
