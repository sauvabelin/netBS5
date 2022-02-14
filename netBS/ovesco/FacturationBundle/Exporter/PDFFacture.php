<?php

namespace Ovesco\FacturationBundle\Exporter;

use NetBS\CoreBundle\Exporter\PDFPreviewer;
use NetBS\CoreBundle\Utils\Countries;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseMembre;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Form\FactureConfigType;
use Ovesco\FacturationBundle\Model\FactureConfig;
use Ovesco\FacturationBundle\Util\BVR;

class PDFFacture extends BaseFactureExporter
{
    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias()
    {
        return 'pdf.factures';
    }

    /**
     * Returns a displayable name of this exporter
     * @return string
     */
    public function getName()
    {
        return "Imprimer les factures BVR";
    }

    /**
     * Returns this exporter category, IE pdf, excel...
     * @return string
     */
    public function getCategory()
    {
        return 'pdf';
    }

    protected function printDetails(Facture $facture, \FPDF $fpdf) {

        $config = $this->getConfiguration();
        $debiteur = $facture->getDebiteur();

        // Print BVR stuff
        $ref    = BVR::getReferenceNumber($facture);
        $ms     = $config->margeHaut;
        $mg     = $config->margeGauche;
        $haddr  = $ms + $config->haddr;
        $hg     = $ms + $config->hg;
        $waddr  = $mg + $config->waddr;
        $wg     = $mg - $config->wg;
        $wd     = $mg + $config->wd;
        $hd     = $ms + $config->hd;
        $wb     = $mg + $config->wb;
        $hb     = $ms + $config->hb;
        $il     = $config->bvrIl;
        $compte = $facture->getCompteToUse();

        //Adresse haut gauche
        $fpdf->SetFont('Arial', '', 9);
        $this->printBvrBsAdresse($fpdf, $wg, $haddr, $il, $compte);

        //Adresse haut droite
        $this->printBvrBsAdresse($fpdf, $waddr, $haddr, $il, $compte);

        //CCP
        $fpdf->SetFontSize(11);
        $fpdf->SetFont('Arial', '', 9);
        $fpdf->SetXY($mg + $config->wccp , $ms + $config->hccp);
        $fpdf->Cell(50, $il, $compte->getCcp());

        //ligne codage gauche + adresse bas gauche
        $refNumber = sprintf("%s %s %s %s %s %s", substr($ref[1], 0, 2), substr($ref[1], 2, 5), substr($ref[1], 7, 5),
            substr($ref[1], 12, 5), substr($ref[1], 17, 5), substr($ref[1], 22, 5));
        $fpdf->SetFont('Arial', '', 9);
        $fpdf->SetXY($wg , $hg);
        $fpdf->Cell(10, $il, $refNumber);
        $this->printBvrDebiteurAdresse($fpdf, $wg, $hg + $il, $il, $debiteur);

        //ligne codage droite
        $fpdf->SetFont('BVR', '', 11);
        $fpdf->SetXY($wd ,$hd);
        $fpdf->Cell(10, $il, $refNumber);


        //adresse bas droite
        $fpdf->SetFont('Arial', '', 9);
        $this->printBvrDebiteurAdresse($fpdf, $wd, $hd + $il*6, $il, $debiteur);

        //ligne codage bas
        $fpdf->SetFont('BVR', '', 12.8);
        $fpdf->SetXY($wb, $hb);
        $fpdf->Cell(0, $il, "$ref[0]>$ref[1]+ $ref[2]>");

        //Points de contoles visuels
        $fpdf->Line($wd+49,$hb-4.5,$wd+52,$hb-4.5);
        $fpdf->Line($wd-1.5,$hg+9,$wd-1.5,$hg+11);

    }

    /**
     * @param \FPDF $fpdf
     * @param $x
     * @param $y
     * @param $interligne
     * @param BaseFamille|BaseMembre $debiteur
     */
    private function printBvrDebiteurAdresse(\FPDF $fpdf, $x, $y, $interligne, $debiteur) {

        $nom = $debiteur instanceof BaseFamille
            ? $debiteur->__toString()
            : $debiteur->getFamille()->getNom() . " " . $debiteur->getPrenom();

        $adresse = $debiteur->getSendableAdresse();

        $fpdf->SetXY($x , $y);
        $fpdf->Cell(10, $interligne, utf8_decode($nom));

        if($adresse) {
            $fpdf->SetXY($x, $y + $interligne);
            $fpdf->Cell(10, $interligne, utf8_decode($adresse->getRue()));
            $fpdf->SetXY($x, $y + $interligne * 2);
            $fpdf->Cell(10, $interligne, utf8_decode($adresse->getNpa(). ' ' . $adresse->getLocalite()));

            if ($adresse->getPays() !== "CH") {
                $fpdf->SetXY($x, $y + $interligne * 3);
                $fpdf->Cell(10, $interligne, utf8_decode(Countries::getName($adresse->getPays())));
            }
        }
    }

    private function printBvrBsAdresse(\FPDF $fpdf, $x, $y, $interligne, Compte $compte) {
        $fpdf->SetXY($x, $y);
        $fpdf->Cell(50, $interligne, $compte->getLine1());
        $fpdf->SetXY($x , $y + $interligne);
        $fpdf->Cell(50, $interligne, $compte->getLine2());
        $fpdf->SetXY($x , $y + 2*$interligne);
        $fpdf->Cell(50, $interligne, $compte->getLine3());
    }

    /**
     * Returns the form used to configure the export
     * @return string
     */
    public function getConfigFormClass()
    {
        return FactureConfigType::class;
    }

    /**
     * Returns the configuration object class
     * @return string
     */
    public function getBasicConfig()
    {
        return new FactureConfig();
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
}
