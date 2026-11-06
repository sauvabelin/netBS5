<?php

namespace Ovesco\FacturationBundle\Service;

use NetBS\FichierBundle\Mapping\BaseFamille;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Old-format facture texts (FactureModel::TEXTES_ANCIEN): the whole text is turned into one
 * expression-language string, with [expression] spliced in. Kept as is for models not yet
 * saved from the new editor, and converted to the new format when such a model is edited.
 */
class LegacyFactureText
{
    /** Known [expression] => new placeholder. */
    const PLACEHOLDERS = [
        'debiteur'                                       => '{debiteur}',
        'facture.getFactureId()'                         => '{facture.numero}',
        'facture.getRappels().count()'                   => '{nombre_rappels}',
        'facture.getCreances().count()'                  => '{nombre_creances}',
        'facture.getLatestImpression().format("d.m.Y")'  => '{derniere_impression}',
        'facture.getDate().format("d.m.Y")'              => '{facture.date}',
    ];

    const PLURAL = '/^\(facture\.getCreances\(\)\.count\(\) == 1 \? "([^"|}]*)" : "([^"|}]*)"\)$/';

    private ExpressionLanguage $engine;

    public function __construct()
    {
        $this->engine = new ExpressionLanguage();
    }

    /** Former BaseFactureExporter::evaluate($string, $facture), unchanged. */
    public function render(?string $text, Facture $facture): string
    {
        if ($text === null) return true;

        $string = str_replace("\r", '', str_replace("\n", '', $text));
        $string = str_replace("'", "\\'", $string);
        $string = str_replace('[', "'~", str_replace("]", "~'", "'$string'"));

        return $this->engine->evaluate($string, [
            'facture' => $facture,
            'debiteur' => $facture->getDebiteur(),
            'isFamille' => $facture->getDebiteur() instanceof BaseFamille
        ]);
    }

    /**
     * Old format => new: real CR/LF dropped; literal segments decoded with stripcslashes (as the old
     * expression string literal did, so "\n" becomes a line break); known [expression] become
     * placeholders, others are kept verbatim as expert placeholders.
     */
    public static function convert(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text  = str_replace(["\r", "\n"], '', $text);
        $parts = preg_split('/\[([^\]]*)\]/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $converted = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $converted .= stripcslashes($part);
            } elseif (isset(self::PLACEHOLDERS[$part])) {
                $converted .= self::PLACEHOLDERS[$part];
            } elseif (preg_match(self::PLURAL, $part, $m)) {
                $converted .= '{pluriel_creances:' . $m[1] . '|' . $m[2] . '}';
            } else {
                $converted .= '[' . $part . ']';
            }
        }

        return $converted;
    }
}
