<?php
// Page de modification d'une ressource.
// On ne peut pas changer le type (document ou favori).
require_once "includes/fonctions.php";

// on verifie l'id recu dans l'url (?id=...)
$id = lireIdDepuisUrl();
$ressource = null;
if ($id !== null) {
    $ressource = getDocumentById($id);
}

$erreurs = [];

if ($ressource === null) {
    http_response_code(404); // la page affichera "Ressource introuvable"
} else {

    // le type est toujours lu dans le fichier et jamais dans le formulaire,
    // comme ca on ne peut pas transformer un document en favori
    $estDocument = ($ressource["type"] ?? "") === "document";

    // le formulaire est prerempli avec les valeurs actuelles
    $titre = $ressource["titre"] ?? "";
    $motsCles = $ressource["mots-cles"] ?? "";
    $description = $ressource["description"] ?? "";
    $url = $ressource["url"] ?? "";
    $fichierActuel = basename($ressource["fichier"] ?? "");

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        if (requeteTropVolumineuse()) {
            $erreurs[] = "Les données envoyées dépassent la taille maximale acceptée par le serveur ("
                . ini_get("post_max_size") . "). Choisissez un fichier plus léger.";
        } else {

            $titre = lireChampPost("titre");
            $motsCles = lireChampPost("mots-cles");
            $description = lireChampPost("description");
            $url = lireChampPost("url");
            $fichier = $_FILES["fichier"] ?? null;

            // si aucun nouveau fichier n'est choisi on garde l'ancien
            $remplacerFichier = $estDocument && isset($fichier["error"]) && $fichier["error"] !== UPLOAD_ERR_NO_FILE;

            if (nettoyerTexte($titre) === "") {
                $erreurs[] = "Le titre est obligatoire.";
            } elseif (mb_strlen($titre) > 150) {
                $erreurs[] = "Le titre ne doit pas dépasser 150 caractères.";
            }

            $fichierOk = false;
            if ($remplacerFichier) {
                $erreurFichier = verifierFichier($fichier);
                if ($erreurFichier === null) {
                    $fichierOk = true;
                } else {
                    $erreurs[] = $erreurFichier;
                }
            }

            if (!$estDocument) {
                if ($url === "") {
                    $erreurs[] = "L'adresse (URL) est obligatoire pour un favori.";
                } elseif (!urlValide($url)) {
                    $erreurs[] = "L'adresse doit commencer par http:// ou https:// et ne contenir "
                        . "ni espace ni guillemet (ex : https://www.php.net).";
                }
            }

            if (!is_writable(FICHIER_GED)) {
                $erreurs[] = "Enregistrement impossible : le fichier " . FICHIER_GED . " n'est pas accessible en écriture.";
            }

            if (empty($erreurs)) {
                $guillemets = str_contains($titre . $motsCles . $description, '"');

                $modifications = [
                    "titre" => nettoyerTexte($titre),
                    "mots-cles" => nettoyerTexte(formaterMotsCles($motsCles)),
                    "description" => nettoyerTexte($description, true),
                ];

                if (!$estDocument) {
                    $modifications["url"] = nettoyerTexte($url);
                }

                $nouveauFichier = null;
                if ($remplacerFichier) {
                    $nouveauFichier = enregistrerFichier($fichier);
                    if ($nouveauFichier === null) {
                        $erreurs[] = "Le nouveau fichier n'a pas pu être enregistré dans le dossier uploads/.";
                    } else {
                        $modifications["fichier"] = $nouveauFichier;
                    }
                }

                if (empty($erreurs)) {
                    if (modifier($id, $modifications)) {
                        // on supprime l'ancien fichier seulement quand la modif est bien enregistree
                        if ($nouveauFichier !== null && $fichierActuel !== "" && is_file(DOSSIER_UPLOADS . $fichierActuel)) {
                            unlink(DOSSIER_UPLOADS . $fichierActuel);
                        }

                        $retour = "index.php?message=modification";
                        if ($guillemets) {
                            $retour .= "&guillemets=1";
                        }
                        header("Location: " . $retour);
                        exit;
                    }
                    $erreurs[] = "La ressource n'existe plus : elle a peut-être été supprimée entre-temps.";
                }
            }

            // le navigateur ne garde pas le fichier choisi quand la page se recharge
            if (!empty($erreurs) && $fichierOk) {
                $erreurs[] = "Par sécurité, le navigateur ne conserve pas le fichier choisi : sélectionnez-le à nouveau.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaBox - Modifier une ressource</title>
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
        <div class="carte">
            <?php if ($ressource === null): ?>

                <h1>Ressource introuvable</h1>
                <div class="message message-erreur" role="alert">
                    <p>Aucune ressource ne correspond à cet identifiant : elle a peut-être été supprimée,
                        ou le lien est incorrect.</p>
                </div>
                <p><a class="bouton" href="index.php">Retour à la liste des ressources</a></p>

            <?php else: ?>

                <h1>Modifier une ressource</h1>

                <p class="type-fixe">
                    Type :
                    <?php if ($estDocument): ?>
                        <span class="badge badge-document"><span aria-hidden="true">📄</span> Document</span>
                    <?php else: ?>
                        <span class="badge badge-favori"><span aria-hidden="true">🔗</span> Favori</span>
                    <?php endif; ?>
                    <span class="aide">(le type ne peut pas être modifié)</span>
                </p>

                <?php if (!empty($erreurs)): ?>
                    <div class="message message-erreur" role="alert">
                        <p><strong>Les modifications n'ont pas été enregistrées :</strong></p>
                        <ul>
                            <?php foreach ($erreurs as $erreur): ?>
                                <li><?= echapper($erreur) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="modifier.php?id=<?= urlencode((string) $id) ?>" enctype="multipart/form-data">

                    <div class="champ">
                        <label for="titre">Titre <span class="obligatoire">*</span></label>
                        <input type="text" id="titre" name="titre" maxlength="150" required
                               value="<?= echapper($titre) ?>">
                    </div>

                    <?php if ($estDocument): ?>
                        <div class="champ">
                            <label for="fichier">Remplacer le fichier (facultatif)</label>
                            <p class="fichier-actuel">
                                Fichier actuel :
                                <?php if ($fichierActuel !== "" && is_file(DOSSIER_UPLOADS . $fichierActuel)): ?>
                                    <a href="<?= DOSSIER_UPLOADS . rawurlencode($fichierActuel) ?>" target="_blank"><?= echapper($fichierActuel) ?></a>
                                <?php else: ?>
                                    <span class="alerte">introuvable sur le serveur</span>
                                <?php endif; ?>
                            </p>
                            <input type="file" id="fichier" name="fichier" aria-describedby="aide-fichier"
                                   accept=".<?= implode(",.", EXTENSIONS_AUTORISEES) ?>">
                            <p class="aide" id="aide-fichier">
                                Laissez vide pour garder le fichier actuel.
                                Formats acceptés : <?= implode(", ", EXTENSIONS_AUTORISEES) ?>
                                (taille maximale : <?= echapper(ini_get("upload_max_filesize")) ?>).
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="champ">
                            <label for="url">Adresse du site (URL) <span class="obligatoire">*</span></label>
                            <input type="url" id="url" name="url" required placeholder="https://..."
                                   value="<?= echapper($url) ?>">
                        </div>
                    <?php endif; ?>

                    <div class="champ">
                        <label for="mots-cles">Mots-clés</label>
                        <input type="text" id="mots-cles" name="mots-cles" aria-describedby="aide-mots-cles"
                               value="<?= echapper($motsCles) ?>">
                        <p class="aide" id="aide-mots-cles">Séparés par des virgules, par exemple : rock, guitare, démo</p>
                    </div>

                    <div class="champ">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="5"><?= echapper($description) ?></textarea>
                    </div>

                    <div class="boutons-formulaire">
                        <button type="submit" class="bouton">Enregistrer les modifications</button>
                        <a class="bouton bouton-secondaire" href="index.php">Annuler</a>
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
