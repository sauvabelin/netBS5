<?php

namespace Ovesco\FacturationBundle\Exporter;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Exporter\PDFPreviewer;
use NetBS\CoreBundle\Model\ConfigurableExporterInterface;
use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\CoreBundle\Utils\Countries;
use NetBS\CoreBundle\Utils\StrUtil;
use NetBS\CoreBundle\Utils\Traits\ConfigurableExporterTrait;
use NetBS\FichierBundle\Mapping\BaseFamille;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Model\FactureConfig;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseFactureExporter implements ExporterInterface, ConfigurableExporterInterface
{
    use ConfigurableExporterTrait;

    private $manager;

    private $engine;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
        $this->engine = new ExpressionLanguage();
    }

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass()
    {
        return Facture::class;
    }

    /**
     * Returns this exporter category, IE pdf, excel...
     * @return string
     */
    public function getCategory()
    {
        return 'pdf';
    }

    abstract protected function printDetails(Facture $facture, \FPDF $fpdf);

    /**
     * Returns a valid response to be returned directly
     * @param Facture[] $items
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function export($items)
    {
        define('FPDF_FONTPATH', __DIR__ . '/Facture/fonts/');

        /** @var FactureConfig $config */
        $config = $this->getConfiguration();
        $fpdf   = new \FPDF();
        $fpdf->SetLeftMargin($config->margeGauche);
        $fpdf->SetRightMargin($config->margeGauche);
        $fpdf->SetTopMargin($config->margeHaut);
        $fpdf->SetAutoPageBreak(true, 0);
        $fpdf->AddFont('OpenSans', '', 'OpenSans-Regular.php');
        $fpdf->AddFont('OpenSans', 'B', 'OpenSans-Bold.php');
        $fpdf->AddFont('Arial', '', 'arial.php');
        $fpdf->AddFont('Arial', 'B', 'arialbd.php');
        $fpdf->AddFont('BVR', '', 'ocrb10n.php');

        /** @var Facture[] $noAdress */
        $noAdress = [];
        foreach($items as $facture)
            if (!$facture->getDebiteur()->getSendableAdresse())
                $noAdress[] = $facture;

        if (count($noAdress) > 0) {
            $text = "Certaines factures sont adressées à des débiteurs n'ayant aucune adresse!\n" .
                "Les factures suivantes ne seront pas générées:\n";
            foreach($noAdress as $facture)
                $text .= " - {$facture->__toString()}, montant total: {$facture->getMontant()}\n";

            $fpdf->AddPage();
            $fpdf->SetFont('OpenSans', '', 10);
            $fpdf->MultiCell(200, 6, utf8_decode($text));
        }

        foreach($items as $facture)
            $this->printFacture($facture, $fpdf);

        foreach($items as $facture) {
            if (!$facture->hasBeenPrinted())
                $facture->setLatestImpression(new \DateTime());
        }

        // We've set impression date
        $this->manager->flush();

        return new StreamedResponse(function() use ($fpdf) {
            $fpdf->Output();
        });
    }

    private function getModel(Facture $facture) {

        $modelId = $this->getConfiguration()->model;
        if (is_int($modelId)) return $this->manager->getRepository('OvescoFacturationBundle:FactureModel')
            ->find($modelId);
        else {
            $models = $this->manager->getRepository('OvescoFacturationBundle:FactureModel')
                ->createQueryBuilder('m')->orderBy('m.poids', 'DESC')->getQuery()->getResult();

            /** @var FactureModel $item */
            foreach($models as $item)
                if ($this->evaluate($item->getApplicationRule(), $facture, false))
                    return $item;

            return $models[0];
        }
    }

    private function evaluate($string, Facture $facture, $parse = true) {

        if ($string === null) return true;

        if($parse) {
            $string = str_replace("\r", '', str_replace("\n", '', $string));
            $string = str_replace("'", "\\'", $string);
            $string = str_replace('[', "'~", str_replace("]", "~'", "'$string'"));
        }

        $res = $this->engine->evaluate($string, [
            'facture' => $facture,
            'debiteur' => $facture->getDebiteur(),
            'isFamille' => $facture->getDebiteur() instanceof BaseFamille
        ]);

        return $res;
    }

    private function factureLatestDate(Facture $facture) {
        if (count($facture->getRappels()) === 0) return $facture->getDate();
        return $facture->getLatestRappel()->getDate();
    }

    private function printFacture(Facture $facture, \FPDF $fpdf) {

        if (!$facture->getDebiteur()->getSendableAdresse()) return;

        /** @var FactureConfig $config */
        $config = $this->getConfiguration();
        $model = $this->getModel($facture);
        $date = $config->date instanceof \DateTime ? $config->date : $this->factureLatestDate($facture);
        $fpdf->AddPage();
        $fpdf->Image(__DIR__ . '/Facture/logo.png', 15, 20, 16, 16);
        $fpdf->SetFont('OpenSans', 'B', 10);

        // Print adresse
        $fpdf->SetXY(35, 17);
        $fpdf->Cell(50, 10, $model->getGroupName());

        $fpdf->SetFont('OpenSans', '', 9);
        $fpdf->SetXY(35, 21);
        $fpdf->Cell(50, 10, utf8_decode($model->getRue()));

        $fpdf->SetXY(35, 25);
        $fpdf->Cell(50, 10, utf8_decode($model->getNpaVille()));

        $fpdf->SetXY(35, 29);
        $fpdf->Cell(50, 10, utf8_decode("Suisse"));

        // Print date and destinataire
        $fpdf->SetXY(130, 17);
        $printDate = $date->format('d') . " " .$this->toMois($date->format('m')) . " " . $date->format('Y');
        $fpdf->Cell(50, 10, utf8_decode($model->getCityFrom() . " le $printDate"));

        $debiteur = $facture->getDebiteur();
        $adresse = $debiteur->getSendableAdresse();
        $adresseIndex = 0;
        if($adresse) {
            $title = $debiteur->__toString();
            if ($debiteur instanceof BaseFamille  && $adresse->getPays() === "CH") {
                $debiteurs = [];
                foreach($facture->getCreances() as $creance)
                    $debiteurs[$creance->_getDebiteurId()] = $creance->getDebiteur();
                if (count($debiteurs) === 1) {
                    $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4 * $adresseIndex++);
                    $fpdf->Cell(50, 10, utf8_decode("Aux parents de"));
                    $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4*$adresseIndex++);
                    $fpdf->Cell(50, 10, utf8_decode(array_pop($debiteurs)->__toString()));
                }
            }

            if ($adresseIndex === 0) {
                $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4*$adresseIndex++);
                $fpdf->Cell(50, 10, utf8_decode($title));
            }

            $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4*$adresseIndex++);
            $fpdf->Cell(50, 10, utf8_decode($adresse->getRue()));

            $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4*$adresseIndex++);
            $fpdf->Cell(50, 10, $adresse->getNpa() . " " . utf8_decode($adresse->getLocalite()));

            if ($adresse->getPays() !== "CH") {
                $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4 * $adresseIndex);
                $fpdf->Cell(50, 10, utf8_decode(Countries::getName($adresse->getPays())));
            }
        }

        // Print title
        $fpdf->SetXY(15, 60);
        $fpdf->SetFont('OpenSans', 'B', 10);
        $fpdf->Cell(0, 10, utf8_decode(strtoupper(StrUtil::removeAccents($this->evaluate($model->getTitre(), $facture)))));

        $fpdf->SetXY(15.2, 65);
        $fpdf->SetFont('OpenSans', '', 7);
        $fpdf->Cell(20, 10, 'N/Ref : ' . $facture->getFactureId());

        $fpdf->SetXY(15, 75);
        $fpdf->SetFontSize(10);
        $fpdf->MultiCell(0, $config->interligne, utf8_decode($this->evaluate($model->getTopDescription(), $facture)), 0);
        $currentY = $fpdf->GetY() + 2;

        $fpdf->SetFontSize(9);

        $i = 0;
        /** @var Creance[] $creances */
        $creances = $facture->getCreances()->toArray();
        for(; $i < count($creances); $i++) {
            $creance = $creances[$i];
            $rbs = '';

            $both = $creance->rabaisFamilleApplicable() && $creance->getRabaisIfInFamille() > 0 && $creance->getRabais() > 0;
            if ($creance->getRabais() > 0) $rbs .= "Rabais " . $creance->getRabais() . "%";
            if ($creance->rabaisFamilleApplicable() && $creance->getRabaisIfInFamille() > 0) $rbs .= (($both ? ' - ' : '') . "Rabais famille " . $creance->getRabaisIfInFamille() . "%");
            $this->printCreanceLine($fpdf, $currentY, $i, $creances[$i]->getTitre() . (strlen($rbs) ? " ($rbs)" : ''), $creances[$i]->getActualMontant());
        }

        if(count($facture->getPaiements()) > 0)
            $this->printCreanceLine($fpdf, $currentY, $i++, "Montant déjà payé", -($facture->getMontantPaye()));

        if(count($creances) > 1 || count($facture->getPaiements()) > 0)
            $this->printCreanceLine($fpdf, $currentY, $i++, "Total", $facture->getMontantEncoreDu(), true);

        $currentY = $fpdf->GetY() + $config->interligne*2;

        $fpdf->SetFontSize(10);
        $fpdf->SetXY(15, $currentY);
        $fpdf->MultiCell(0, $config->interligne, $this->evaluate(utf8_decode($model->getBottomSalutations()), $facture));

        // Signature
        $fpdf->SetXY(130, $fpdf->GetY() + $config->interligne);
        $fpdf->Cell(50, 10, utf8_decode($model->getSignataire()));

        $this->printDetails($facture, $fpdf);
    }

    private function printCreanceLine(\FPDF $fpdf, $baseY, $i, $titre, $montant, $bold = false) {

        if($bold) {
            $fpdf->SetFont('OpenSans', 'B');
        }

        $fpdf->SetXY(15, $baseY + ($i*6));
        $fpdf->Cell(0, 6, utf8_decode($titre), 1);

        $fpdf->SetXY(170, $baseY + ($i*6));
        $montant = number_format($montant, 2, '.', "'");
        $fpdf->Cell(0, 6, 'CHF ' . $montant, 'L', 'ln', 'R');

        if($bold) {
            $fpdf->SetFont('OpenSans', '');
        }
    }

    /**
     * If the rendered file can be previewed, return the used
     * previewer class
     * @return string
     */
    public function getPreviewer()
    {
        return PDFPreviewer::class;
    }

    private function toMois($mois) {
        return (['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre',
            'Octobre', 'Novembre', 'Décembre'])[intval($mois) - 1];
    }
}
