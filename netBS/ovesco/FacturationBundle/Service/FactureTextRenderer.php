<?php

namespace Ovesco\FacturationBundle\Service;

use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Rule\FactureContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Renders a facture model's titre / texte du haut / texte du bas. New format: the text as
 * written, with {placeholder} replaced and [expression] (expert, admins only) evaluated.
 */
class FactureTextRenderer
{
    /** {name} or {name:arguments}, or [expression] */
    const TOKEN = '/\{([a-z_.]+)(?::([^}]*))?\}|\[([^\]]*)\]/';

    private ExpressionLanguage $engine;

    public function __construct(
        private LegacyFactureText $legacy,
        private FactureContext $context,
        private LoggerInterface $logger,
    ) {
        $this->engine = new ExpressionLanguage();
    }

    /** @return array<string,string> placeholder => French description */
    public function placeholders(): array
    {
        return [
            '{debiteur}'                           => 'Le débiteur (membre ou « Famille Nom »)',
            '{facture.numero}'                     => 'Le numéro de la facture',
            '{facture.date}'                       => 'La date de la facture (jj.mm.aaaa)',
            '{derniere_impression}'                => 'La date de la dernière impression (facture ou rappel), vide si jamais imprimée',
            '{nombre_rappels}'                     => 'Le nombre de rappels',
            '{nombre_creances}'                    => 'Le nombre de créances',
            '{pluriel_creances:singulier|pluriel}' => 'Le texte « singulier » pour une seule créance, sinon « pluriel »',
        ];
    }

    /** @return list<string> placeholder names, as written between the braces before any ":" */
    public static function names(): array
    {
        return ['debiteur', 'facture.numero', 'facture.date', 'derniere_impression', 'nombre_rappels', 'nombre_creances', 'pluriel_creances'];
    }

    public function render(?string $text, Facture $facture, int $format): string
    {
        if ($format === FactureModel::TEXTES_ANCIEN) {
            return $this->legacy->render($text, $facture);
        }

        $text = str_replace("\r\n", "\n", (string) $text);

        return preg_replace_callback(self::TOKEN, function (array $m) use ($facture) {
            if (isset($m[3])) {
                return $this->expression($m[3], $facture);
            }

            return $this->placeholder($m[1], $m[2] ?? '', $facture) ?? $m[0];
        }, $text);
    }

    private function placeholder(string $name, string $arguments, Facture $facture): ?string
    {
        return match ($name) {
            'debiteur'            => (string) $facture->getDebiteur(),
            'facture.numero'      => (string) $facture->getFactureId(),
            'facture.date'        => $facture->getDate()?->format('d.m.Y') ?? '',
            'derniere_impression' => $facture->getLatestImpression()?->format('d.m.Y') ?? '',
            'nombre_rappels'      => (string) count($facture->getRappels()),
            'nombre_creances'     => (string) count($facture->getCreances()),
            'pluriel_creances'    => explode('|', $arguments, 2)[count($facture->getCreances()) === 1 ? 0 : 1] ?? '',
            default               => null,
        };
    }

    private function expression(string $expression, Facture $facture): string
    {
        try {
            return (string) $this->engine->evaluate($expression, $this->context->expressionValues($facture));
        } catch (\Throwable $e) {
            $this->logger->warning('Facture text expression [{expression}] failed, rendered empty: {message}', [
                'expression' => $expression,
                'message'    => $e->getMessage(),
            ]);

            return '';
        }
    }
}
