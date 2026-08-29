<?php

namespace Database\Seeders;

use App\Models\CatecheseConfiguration;
use App\Models\ModeleDocument;
use Illuminate\Database\Seeder;

class DocumentModeleSeeder extends Seeder
{
    public function run(): void
    {
        $paroisse = CatecheseConfiguration::first();
        $paroisseId = $paroisse?->id;

        $modeles = [
            [
                'code'          => 'CERT_BAPTEME',
                'titre'         => 'Certificat de Baptême',
                'type_document' => 'certificat',
                'description'   => 'Certificat solennel attestant la réception du Sacrement du Baptême.',
                'signature_titre' => 'Le Curé de la Paroisse',
                'signature_nom'   => $paroisse?->cure_nom ?? 'Père Curé',
                'is_system'       => true,
                'contenu'       => '<div class="document-container cert-bapteme">
  <div class="doc-header text-center">
    <h2>DIOCÈSE DE {{paroisse_diocese}}</h2>
    <h3>PAROISSE {{paroisse_nom}}</h3>
    <p class="doc-badge">EXTRAIT DU REGISTRE DES BAPTÊMES</p>
  </div>
  <div class="doc-body">
    <p>Nous soussigné, Curé de la Paroisse <strong>{{paroisse_nom}}</strong>, certifions que :</p>
    <div class="doc-highlight-box">
      <h1 class="person-name">{{nom_complet}}</h1>
      <p>Matricule : <strong>{{matricule}}</strong></p>
      <p>Né(e) le <strong>{{date_naissance}}</strong> à <strong>{{lieu_naissance}}</strong></p>
      <p>Fils/Fille de <strong>{{pere_nom}}</strong> et de <strong>{{mere_nom}}</strong></p>
    </div>
    <p>A reçu le Sacrement du Saint Baptême le <strong>{{date_bapteme}}</strong> en l\'église <strong>{{lieu_bapteme}}</strong>.</p>
    <p>Parrain / Marraine : <strong>{{parrain_marraine}}</strong></p>
    <p>En foi de quoi le présent certificat lui est délivré pour servir et valoir ce que de droit.</p>
  </div>
  <div class="doc-footer">
    <div class="date-place">Fait à {{paroisse_ville}}, le {{date_du_jour}}</div>
    <div class="signature-block">
      <p class="sign-title">{{paroisse_cure}}</p>
      <p class="sign-sub">Le Curé de la Paroisse</p>
      <div class="stamp-space">[ Sceau & Cachet de la Paroisse ]</div>
    </div>
  </div>
</div>',
            ],
            [
                'code'          => 'CERT_COMMUNION',
                'titre'         => 'Attestation de Première Communion',
                'type_document' => 'certificat',
                'description'   => 'Attestation officielle de la Première Communion (Sacrement de l\'Eucharistie).',
                'signature_titre' => 'Le Curé de la Paroisse',
                'signature_nom'   => $paroisse?->cure_nom ?? 'Père Curé',
                'is_system'       => true,
                'contenu'       => '<div class="document-container cert-communion">
  <div class="doc-header text-center">
    <h2>PAROISSE {{paroisse_nom}}</h2>
    <p class="doc-badge">SACREMENT DE L\'EUCHARISTIE</p>
    <h3>ATTESTATION DE PREMIÈRE COMMUNION</h3>
  </div>
  <div class="doc-body">
    <p>Il est certifié par la présente que :</p>
    <div class="doc-highlight-box">
      <h1 class="person-name">{{nom_complet}}</h1>
      <p>Matricule Catéchèse : <strong>{{matricule}}</strong> • Classe : <strong>{{classe}}</strong></p>
    </div>
    <p>A fait sa Première Communion Solennelle le <strong>{{date_premiere_communion}}</strong> en l\'église <strong>{{paroisse_nom}}</strong> après avoir suivi avec assiduité la préparation requise au cours de l\'année pastorale <strong>{{annee_pastorale}}</strong>.</p>
  </div>
  <div class="doc-footer">
    <div class="date-place">Délivré à {{paroisse_ville}}, le {{date_du_jour}}</div>
    <div class="signature-block">
      <p class="sign-title">{{paroisse_cure}}</p>
      <p class="sign-sub">Le Curé de la Paroisse</p>
    </div>
  </div>
</div>',
            ],
            [
                'code'          => 'CERT_CONFIRMATION',
                'titre'         => 'Attestation de Confirmation',
                'type_document' => 'certificat',
                'description'   => 'Certificat officiel du Sacrement de la Confirmation conféré par l\'Évêque.',
                'signature_titre' => 'Le Curé de la Paroisse',
                'signature_nom'   => $paroisse?->cure_nom ?? 'Père Curé',
                'is_system'       => true,
                'contenu'       => '<div class="document-container cert-confirmation">
  <div class="doc-header text-center">
    <h2>DIOCÈSE DE {{paroisse_diocese}}</h2>
    <h3>PAROISSE {{paroisse_nom}}</h3>
    <p class="doc-badge">SACREMENT DE LA CONFIRMATION</p>
  </div>
  <div class="doc-body">
    <p>Nous attestons que le/la confirmé(e) :</p>
    <div class="doc-highlight-box">
      <h1 class="person-name">{{nom_complet}}</h1>
      <p>Né(e) le <strong>{{date_naissance}}</strong> à <strong>{{lieu_naissance}}</strong></p>
      <p>Matricule : <strong>{{matricule}}</strong></p>
    </div>
    <p>A reçu l\'Onction du Saint Chrême et le Don de l\'Esprit Saint pour le Sacrement de Confirmation le <strong>{{date_confirmation}}</strong>.</p>
    <p>Parrain / Marraine de confirmation : <strong>{{parrain_marraine}}</strong></p>
  </div>
  <div class="doc-footer">
    <div class="date-place">Fait à {{paroisse_ville}}, le {{date_du_jour}}</div>
    <div class="signature-block">
      <p class="sign-title">{{paroisse_cure}}</p>
      <p class="sign-sub">Le Curé</p>
    </div>
  </div>
</div>',
            ],
            [
                'code'          => 'ATTEST_CATECHESE',
                'titre'         => 'Attestation de Suivi de Catéchèse',
                'type_document' => 'attestation',
                'description'   => 'Attestation de fréquentation et d\'assiduité aux cours de catéchèse.',
                'signature_titre' => 'La Coordination Pastorale',
                'signature_nom'   => $paroisse?->coordination_nom ?? 'Coordination Catéchèse',
                'is_system'       => true,
                'contenu'       => '<div class="document-container attest-catechese">
  <div class="doc-header text-center">
    <h2>PAROISSE {{paroisse_nom}}</h2>
    <p class="doc-badge">BUREAU DE LA CATÉCHÈSE</p>
    <h3>ATTESTATION DE SUIVI DES COURS DE CATÉCHÈSE</h3>
  </div>
  <div class="doc-body">
    <p>La Coordination Pastorale de la Catéchèse atteste que l\'enfant / jeune :</p>
    <div class="doc-highlight-box">
      <h1 class="person-name">{{nom_complet}}</h1>
      <p>Matricule : <strong>{{matricule}}</strong></p>
      <p>Section : <strong>{{section}}</strong> • Niveau : <strong>{{niveau}}</strong> • Classe : <strong>{{classe}}</strong></p>
    </div>
    <p>A suivi régulièrement les cours d\'initiation chrétienne et de formation catéchétique au titre de l\'année pastorale <strong>{{annee_pastorale}}</strong>.</p>
    <p>La présente attestation est délivrée à la demande de ses parents pour servir et valoir ce que de droit.</p>
  </div>
  <div class="doc-footer">
    <div class="date-place">Fait à {{paroisse_ville}}, le {{date_du_jour}}</div>
    <div class="signature-block">
      <p class="sign-title">La Coordination Pastorale de la Catéchèse</p>
    </div>
  </div>
</div>',
            ],
            [
                'code'          => 'FICHE_INSCRIPTION',
                'titre'         => 'Fiche d\'Inscription Officielle',
                'type_document' => 'fiche',
                'description'   => 'Fiche récapitulative officielle d\'inscription annuelle du catéchumène.',
                'signature_titre' => 'Le Secrétariat Paroissial',
                'signature_nom'   => 'Secrétariat Catéchèse',
                'is_system'       => true,
                'contenu'       => '<div class="document-container fiche-inscription">
  <div class="doc-header text-center">
    <h2>PAROISSE {{paroisse_nom}}</h2>
    <h3>FICHE INDIVIDUELLE D\'INSCRIPTION À LA CATÉCHÈSE</h3>
    <p class="doc-badge">ANNÉE PASTORALE {{annee_pastorale}}</p>
  </div>
  <div class="doc-body">
    <table class="doc-table">
      <tr><td><strong>Matricule :</strong></td><td>{{matricule}}</td><td><strong>Date d\'enregistrement :</strong></td><td>{{date_du_jour}}</td></tr>
      <tr><td><strong>Nom et Prénoms :</strong></td><td colspan="3"><strong>{{nom_complet}}</strong></td></tr>
      <tr><td><strong>Date et lieu de naissance :</strong></td><td colspan="3">{{date_naissance}} à {{lieu_naissance}}</td></tr>
      <tr><td><strong>Filiation :</strong></td><td colspan="3">Père : {{pere_nom}} • Mère : {{mere_nom}}</td></tr>
      <tr><td><strong>Affectation Pastorale :</strong></td><td colspan="3">Niveau : {{niveau}} | Classe : {{classe}}</td></tr>
      <tr><td><strong>Parrain / Marraine :</strong></td><td colspan="3">{{parrain_marraine}}</td></tr>
    </table>
  </div>
  <div class="doc-footer">
    <div class="signature-block">
      <p class="sign-sub">Signature des Parents / Tuteurs</p>
    </div>
    <div class="signature-block right">
      <p class="sign-sub">Visa du Secrétariat</p>
    </div>
  </div>
</div>',
            ],
            [
                'code'          => 'CONVOCATION_PARENTS',
                'titre'         => 'Convocation Pastorale des Parents',
                'type_document' => 'convocation',
                'description'   => 'Lettre officielle de convocation des parents de catéchumènes pour réunion ou rencontre.',
                'signature_titre' => 'L\'Aumônier de la Catéchèse',
                'signature_nom'   => 'Père Aumônier',
                'is_system'       => true,
                'contenu'       => '<div class="document-container convocation-parents">
  <div class="doc-header text-center">
    <h2>PAROISSE {{paroisse_nom}}</h2>
    <h3>AVIS & CONVOCATION DES PARENTS DE CATÉCHUMÈNE</h3>
  </div>
  <div class="doc-body">
    <p>Chers Parents de l\'enfant <strong>{{nom_complet}}</strong> (Classe : {{classe}}),</p>
    <p>Vous êtes cordialement invités à prendre part à la rencontre pastorale des parents qui se tiendra en la Salle Paroissiale.</p>
    <p>Votre présence en tant que premiers éducateurs de la foi de vos enfants est vivement souhaitée.</p>
    <p>Que le Seigneur bénisse vos familles et votre engagement.</p>
  </div>
  <div class="doc-footer">
    <div class="date-place">Fait à {{paroisse_ville}}, le {{date_du_jour}}</div>
    <div class="signature-block">
      <p class="sign-sub">L\'Aumônier Paroissial</p>
    </div>
  </div>
</div>',
            ],
        ];

        foreach ($modeles as $m) {
            ModeleDocument::updateOrCreate(
                [
                    'paroisse_configuration_id' => $paroisseId,
                    'code'                      => $m['code'],
                ],
                $m
            );
        }
    }
}
