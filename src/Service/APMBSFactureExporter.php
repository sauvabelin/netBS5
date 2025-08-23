<?php

namespace App\Service;

use App\Entity\APMBSReservation;
use App\Entity\ReservationLog;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Exporter\PDFPreviewer;
use NetBS\CoreBundle\Utils\StrUtil;
use Ovesco\FacturationBundle\Entity\Compte;
use Sprain\SwissQrBill\QrBill;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Model\FactureConfig;
use Ovesco\FacturationBundle\Model\QrFactureConfig;
use Sprain\SwissQrBill\DataGroup\Element\CombinedAddress;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;
use Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator;

class APMBSFactureExporter
{
    const PART_HEIGHT = 105;
    const A4_HEIGHT = 297;
    const PAYMENT_WIDTH = 148;
    const DEBTOR_WIDTH = 62;
    const PART_MARGIN = 5;


    private $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass()
    {
        return APMBSReservation::class;
    }

    /**
     * Returns this exporter category, IE pdf, excel...
     * @return string
     */
    public function getCategory()
    {
        return 'pdf';
    }


    /**
     * Returns a valid response to be returned directly
     * @param APMBSReservation[] $items
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function generate($items)
    {
        define('FPDF_FONTPATH', __DIR__ . '/Facture/fonts/');

        /** @var FactureConfig $config */
        $config = new QrFactureConfig();
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


        foreach($items as $reservation)
            $this->printFacture($reservation, $fpdf);

        return $fpdf;
    }

    private function getModel() {

        $models = $this->manager->getRepository(FactureModel::class)
            ->createQueryBuilder('m')->orderBy('m.poids', 'DESC')->getQuery()->getResult();

        /** @var FactureModel $item */
        foreach($models as $item)
            if ($item->getName() === 'APMBS')
                return $item;

        return $models[0];
    }

    /**
     * @return Compte|null
     */
    private function getCompteToUse() {
        return $this->manager->getRepository(Compte::class)->findOneBy(['nom' => 'APMBS']);
    }

    private function printFacture(APMBSReservation $reservation, \FPDF $fpdf) {

        /** @var FactureConfig $config */
        $config = new QrFactureConfig();
        $model = $this->getModel($reservation);
        $date = $reservation->getEnd();
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

        $adresseIndex = 0;
        $title = $reservation->getPrenom() . " " . $reservation->getNom();

        if ($adresseIndex === 0) {
            $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4*$adresseIndex++);
            $fpdf->Cell(50, 10, utf8_decode($title));
        }

        $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4*$adresseIndex++);
        $fpdf->Cell(50, 10, utf8_decode($reservation->getRue()));

        $fpdf->SetXY($config->adresseLeft, $config->adresseTop + 4*$adresseIndex++);
        $fpdf->Cell(50, 10, $reservation->getNpa() . " " . utf8_decode($reservation->getLocalite()));

        // Print title
        $fpdf->SetXY(15, 60);
        $fpdf->SetFont('OpenSans', 'B', 10);
        $fpdf->Cell(0, 10, utf8_decode(strtoupper(StrUtil::removeAccents($model->getTitre()))));

        $fpdf->SetXY(15.2, 65);
        $fpdf->SetFont('OpenSans', '', 7);
        $fpdf->Cell(20, 10, 'N/Ref : ' . $reservation->getId());

        $fpdf->SetXY(15, 75);
        $fpdf->SetFontSize(10);
        $fpdf->MultiCell(0, $config->interligne, utf8_decode($model->getTopDescription()), 0);
        $currentY = $fpdf->GetY() + 2;

        $fpdf->SetFontSize(9);

        $total = $reservation->getFinalPrice();
        $creanceTitle = $reservation->getCabane()->getNom();
        $this->printCreanceLine($fpdf, $currentY, 0, $creanceTitle, $reservation->getFinalPrice());

        // Find latest sendInvoice log item
        $logItem = null;
        foreach ($reservation->getLogs() as $log) {
            if ($log->getAction() === ReservationLog::INVOICE_SENT) {
                if (!$logItem || $log->getCreatedAt() > $logItem->getCreatedAt()) {
                    $logItem = $log;
                }
            }
        }

        if ($logItem) {
            $payload = json_decode($logItem->getPayload(), true);
            if (isset($payload['autreFraisMontant']) && isset($payload['autreFraisDescription'])) {
                $total += $payload['autreFraisMontant'];
                $this->printCreanceLine($fpdf, $currentY, 1, $payload['autreFraisDescription'], $payload['autreFraisMontant']);
                $this->printCreanceLine($fpdf, $currentY, 2, "Total", $total, true);
            }
        }
            

        $currentY = $fpdf->GetY() + $config->interligne*2;

        $fpdf->SetFontSize(10);
        $fpdf->SetXY(15, $currentY);
        $fpdf->MultiCell(0, $config->interligne, utf8_decode($model->getBottomSalutations()));

        // Signature
        $fpdf->SetXY(130, $fpdf->GetY() + $config->interligne);
        $fpdf->Cell(50, 10, utf8_decode($model->getSignataire()));

        $this->printDetails($reservation, $fpdf);
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

        protected function printDetails(APMBSReservation $reservation, \FPDF $fpdf) {

        $qrData = $this->getQRData($reservation);
        $this->printReceiptPart($reservation, $qrData, $fpdf);
        $this->printPaymentPart($reservation, $qrData, $fpdf);
    }

    private function printReceiptPart(APMBSReservation $reservation, QrBill $qrData, \FPDF $fpdf) {

        $top = self::A4_HEIGHT - self::PART_HEIGHT;
        $margin = self::PART_MARGIN;
        $left = 0 + $margin;

        $fpdf->SetXY(0, $top);
        // $fpdf->Cell(self::DEBTOR_WIDTH, self::PART_HEIGHT, '', $this->getConfiguration()->border);

        $compte = $this->getCompteToUse();

        // TITRE
        $fpdf->SetXY($left, $top + $margin);
        $fpdf->SetFont('Arial', 'B', 11);
        $fpdf->Cell(self::DEBTOR_WIDTH - 2*$margin, 7, utf8_decode('Récépissé'));

        // payable to
        $fpdf->SetXY($left, $top + $margin + 7);
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->Cell(self::DEBTOR_WIDTH - 2*$margin, 5, utf8_decode('Compte / Payable à'));


        $fpdf->SetXY($left, $top + $margin + 11);
        $fpdf->SetFont('Arial', '', 8);
        $fpdf->MultiCell(self::DEBTOR_WIDTH - 2*$margin, 4, implode("\n", [
            utf8_decode($compte->getQrIban()),
            utf8_decode($compte->getLine1()),
            utf8_decode($compte->getLine2()),
            utf8_decode($compte->getLine3()),
        ]));

        // Reference
        $fpdf->SetXY($left, $top + $margin + 28);
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->Cell(self::DEBTOR_WIDTH - 2*$margin, 9, utf8_decode('Référence'));
        $fpdf->SetXY($left, $top + $margin + 34);
        $fpdf->SetFont('Arial', '', 8);
        $fpdf->Cell(self::DEBTOR_WIDTH - 2*$margin, 4, $qrData->getPaymentReference()->getFormattedReference());

        // Payable by
        $fpdf->SetXY($left, $top + $margin + 38);
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->Cell(self::DEBTOR_WIDTH - 2*$margin, 9, 'Payable par');

        $fpdf->SetXY($left, $top + $margin + 44);
        $fpdf->SetFont('Arial', '', 8);
        $fpdf->MultiCell(self::DEBTOR_WIDTH - 2*$margin, 4, implode("\n", [
            utf8_decode($reservation->getPrenom() . " " . $reservation->getNom()),
            utf8_decode($reservation->getRue()),
            utf8_decode($reservation->getNpa() . ' ' . $reservation->getLocalite())
        ]));

        // Currency
        $fpdf->SetXY($left, $top + 7 + 56 + $margin);
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->Cell(11, 5, 'Monnaie');
        $fpdf->SetXY($left + 11, $top + 7 + 56 + $margin);
        $fpdf->Cell(10, 5, 'Montant');

        $fpdf->SetXY($left, $top + 7 + 56 + $margin + 4);
        $fpdf->SetFont('Arial', '', 8);
        $fpdf->Cell(12, 5, 'CHF');

        // draw user put amount
        $x = self::DEBTOR_WIDTH - $margin - 30;
        $y = $margin + $top + 7 + 56 + 1;

        /*
        $width = 30;
        $height = 10;
        $fpdf->SetDrawColor(0,0,0);
        $fpdf->SetLineWidth(0.25);
        $fpdf->Line($x, $y, $x + 2, $y);
        $fpdf->Line($x, $y, $x, $y + 1);

        $fpdf->Line($x, $y + $height, $x + 2, $y + $height);
        $fpdf->Line($x, $y + $height - 1, $x, $y + $height);

        $fpdf->Line($x + $width - 2, $y, $x + $width, $y);
        $fpdf->Line($x + $width, $y, $x + $width, $y + 1);

        $fpdf->Line($x + $width - 2, $y + $height, $x + $width, $y + $height);
        $fpdf->Line($x + $width, $y + $height - 1, $x + $width, $y + $height);

        if ($this->getConfiguration()->border)
            $fpdf->SetDrawColor(255,0,0);
        */
        $fpdf->Image(__DIR__ . '/Facture/coin_receipt.png', $x, $y, 30, 10);

        // Acceptance point
        $fpdf->SetFont('Arial', 'B', 6);
        $fpdf->SetXY($left, $top + $margin + 7 + 56 + 14);
        $fpdf->Cell(self::DEBTOR_WIDTH - 2*$margin, 5, utf8_decode('Point de dépôt'), 0, 0, 'R');
    }

    private function printPaymentPart(APMBSReservation $reservation, QrBill $qrData, \FPDF $fpdf) {

        $top = self::A4_HEIGHT - self::PART_HEIGHT;
        $margin = self::PART_MARGIN;
        $left = self::DEBTOR_WIDTH + $margin;

        $fpdf->SetDrawColor(0,0,0);
        $fpdf->SetXY(self::DEBTOR_WIDTH, $top);
        // $fpdf->Cell(self::PAYMENT_WIDTH, self::PART_HEIGHT, '', $this->getConfiguration()->border);

        $compte = $this->getCompteToUse();

        $fpdf->SetXY($left, $top + $margin);
        $fpdf->SetFont('Arial', 'B', 11);
        $fpdf->Cell(self::DEBTOR_WIDTH - 2*$margin, 7, 'Section paiement');

        // Print qr
        $fpdf->Image($qrData->getQrCode('png')->getDataUri('png'), $left, $top + 2*$margin + 7, 46, 46, 'png');

        // Montant
        $fpdf->SetXY($left, $top + 3*$margin + 7 + 46);
        $fpdf->SetFont('Arial', 'B', 8);
        $fpdf->Cell(14, 5, 'Monnaie');
        $fpdf->SetXY($left + 14, $top + 3*$margin + 7 + 46);
        $fpdf->Cell(10, 5, 'Montant');

        $fpdf->SetXY($left, $top + 3*$margin + 7 + 46 + 5);
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->Cell(12, 5, 'CHF');

        // draw user put amount
        $x = $left + 11;
        $y = $top + 3*$margin + 7 + 46 + 6;

        /*
        $width = 40;
        $height = 15;
        $fpdf->SetDrawColor(0,0,0);
        $fpdf->SetLineWidth(0.25);
        $fpdf->Line($x, $y, $x + 2, $y);
        $fpdf->Line($x, $y, $x, $y + 1);

        $fpdf->Line($x, $y + $height, $x + 2, $y + $height);
        $fpdf->Line($x, $y + $height - 1, $x, $y + $height);

        $fpdf->Line($x + $width - 2, $y, $x + $width, $y);
        $fpdf->Line($x + $width, $y, $x + $width, $y + 1);

        $fpdf->Line($x + $width - 2, $y + $height, $x + $width, $y + $height);
        $fpdf->Line($x + $width, $y + $height - 1, $x + $width, $y + $height);

        if ($this->getConfiguration()->border)
            $fpdf->SetDrawColor(255,0,0);
        */
        $fpdf->Image(__DIR__ . '/Facture/coin_paiement.png', $x, $y, 40, 15);

        // More information
        $sleft = $left + 51;
        $fpdf->SetXY($sleft, $top + $margin);
        $fpdf->SetFont('Arial', 'B', 8);
        $fpdf->Cell(14, 5, utf8_decode('Compte / Payable à'));

        // address
        $fpdf->SetXY($sleft, $top + $margin + 5);
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->MultiCell(87, 5, implode("\n", [
            $compte->getQrIban(),
            utf8_decode($compte->getLine1()),
            utf8_decode($compte->getLine2()),
            utf8_decode($compte->getLine3()),
        ]));

        // Référence
        $fpdf->SetXY($sleft, $top + $margin + 25);
        $fpdf->SetFont('Arial', 'B', 8);
        $fpdf->Cell(87, 11, utf8_decode('Référence'));
        $fpdf->SetXY($sleft, $top + $margin + 33);
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->Cell(87, 4, $qrData->getPaymentReference()->getFormattedReference());

        // Informations additionnelles
        $fpdf->SetXY($sleft, $top + $margin + 36);
        $fpdf->SetFont('Arial', 'B', 8);
        $fpdf->Cell(87, 11, utf8_decode('Informations supplémentaires'));
        $fpdf->SetXY($sleft, $top + $margin + 44);
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->Cell(87, 4, utf8_decode("Facture n. " . $reservation->getId()));

        // Payable by
        $fpdf->SetXY($sleft, $top + $margin + 48);
        $fpdf->SetFont('Arial', 'B', 8);
        $fpdf->Cell(87, 11, 'Payable par');

        // address
        $fpdf->SetXY($sleft, $top + $margin + 56);
        $fpdf->SetFont('Arial', '', 10);
        $fpdf->MultiCell(87, 5, implode("\n", [
            utf8_decode($reservation->getPrenom() . " " . $reservation->getNom()),
            utf8_decode($reservation->getRue()),
            utf8_decode($reservation->getNpa() . " " . $reservation->getLocalite()),
        ]));
    }

    private function getQRData(APMBSReservation $reservation) {

        $qrBill = QrBill::create();
        $qrBill->setCreditor(CombinedAddress::create(
            $this->getCompteToUse()->getLine1(),
            $this->getCompteToUse()->getLine2(),
            $this->getCompteToUse()->getLine3(),
            'CH'
        ));

        $qrBill->setCreditorInformation(CreditorInformation::create($this->getCompteToUse()->getQrIban()));

        $qrBill->setUltimateDebtor(CombinedAddress::create(
            $reservation->getPrenom() . " " . $reservation->getNom(),
            $reservation->getRue(),
            $reservation->getNpa() . ' ' . $reservation->getLocalite(),
            'CH'
        ));

        $qrBill->setPaymentAmountInformation(PaymentAmountInformation::create('CHF', null));

        $refNum = QrPaymentReferenceGenerator::generate(null, $reservation->getId());
        $qrBill->setPaymentReference(PaymentReference::create(PaymentReference::TYPE_QR, $refNum));

        return $qrBill;
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
