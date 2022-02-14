<?php

namespace NetBS\FichierBundle\Exporter;


use NetBS\CoreBundle\Exporter\PDFPreviewer;
use NetBS\CoreBundle\Model\ConfigurableExporterInterface;
use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\CoreBundle\Utils\Traits\ConfigurableExporterTrait;
use NetBS\FichierBundle\Exporter\Config\EtiquettesV2Config;
use NetBS\FichierBundle\Form\Export\EtiquettesV2Type;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Model\AdressableInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PDFEtiquettesV2 implements ExporterInterface, ConfigurableExporterInterface
{
    use ConfigurableExporterTrait;

    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias()
    {
        return 'pdf.etiquettes.v2';
    }

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass()
    {
        return AdressableInterface::class;
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
     * Returns a displayable name of this exporter
     * @return string
     */
    public function getName()
    {
        return 'Etiquettes PDF';
    }

    /**
     * Returns a valid response to be returned directly
     * @param AdressableInterface[] $items
     * @return StreamedResponse
     */
    public function export($items)
    {
        /** @var EtiquettesV2Config $config */
        $config = $this->getConfiguration();
        $economies = array_map(function($s) { return intval($s); },
            array_filter(explode(' ', $config->economies), function($s) { return !empty($s); }));
        $noAdress = array_filter($items, function(AdressableInterface $adressable) {
            return $adressable->getSendableAdresse() === null;
        });
        $members = array_diff($items, $noAdress);

        $set = $members;
        if ($config->mergeOption === 1) $set = self::merge($members);
        else if ($config->mergeOption === 2) $set = self::mergeBySameAddress($members);
        $fpdf = new \FPDF();
        $fpdf->SetFont('Arial');
        $fpdf->SetFontSize($config->fontSize);
        $fpdf->SetMargins(0, 0);
        $fpdf->SetAutoPageBreak(false);

        $pageWidth = 210;
        $pageHeight = 297;
        $etiquetteWidth = (($pageWidth - $config->horizontalMargin*2) / $config->columns);
        $etiquetteHeight = (($pageHeight - $config->verticalMargin*2) / $config->rows);
        $perPage = intval($config->rows * $config->columns);
        $page = 0;
        $position = null;

        if (count($noAdress) > 0 || $config->infoPage) {
            $fpdf->AddPage();
            $fpdf->SetXY($config->horizontalMargin, $config->verticalMargin);
            $fpdf->MultiCell(200, $config->interligne, utf8_decode(implode("\n", [
                "Nombre d'étiquettes : " . count($items),
                "Nombre d'étiquettes après fusion : " . count($set),
                "Nombre d'éléments sans adresse: " . count($noAdress),
                "Les sans adresses:",
            ])));

            $fpdf->SetTextColor(255,0,0);
            $fpdf->SetXY($config->horizontalMargin, 30);
            $fpdf->MultiCell(200, $config->interligne, utf8_decode(implode("\n", array_map(function($i) {
                return $i->__toString();
            }, $noAdress))));
            $fpdf->SetTextColor(0,0,0);
        }

        /** @var AdressableInterface $current */
        foreach($set as $current) {

            $adresse = $current->getSendableAdresse();

            if ($position === $perPage || $position === null) {
                if ($position !== null) $page++;

                $fpdf->AddPage();
                $position = isset($economies[$page]) ? $perPage - $economies[$page] : 0;
            }

            $colPos = $position % $config->columns;
            $rowPos = floor($position / $config->columns);
            $x = $config->horizontalMargin + $colPos * $etiquetteWidth;
            $y = $config->verticalMargin + $rowPos * $etiquetteHeight;
            $fpdf->SetDrawColor(255,0,0);
            $fpdf->SetXY($x, $y);
            $fpdf->Cell($etiquetteWidth, $etiquetteHeight, '', $config->reperes);

            // handle margin
            $exactX = $x + $config->paddingLeft;
            $exactY = $y + $config->paddingTop;
            $marginWidth = $etiquetteWidth - $config->paddingLeft;
            // $marginHeight = $etiquetteHeight - $config->paddingTop;
            $fpdf->SetXY($exactX, $exactY);

            // Print etiquette
            $fpdf->SetDrawColor(0,0,255);
            $fpdf->MultiCell($marginWidth, $config->interligne, implode("\n", [
                utf8_decode($current->__toString()),
                utf8_decode($adresse->getRue()),
                utf8_decode($adresse->getNpa() . " " . $adresse->getLocalite()),
            ]), $config->reperes, 'L');

            $position++;
        }

        return  new StreamedResponse(function() use ($fpdf) {
            $fpdf->Output();
        });
    }

    /**
     * @param AdressableInterface[] $adressables
     * @return array
     */
    public static function mergeBySameAddress($adressables) {

        $result = [];
        foreach ($adressables as $adressable) {
            if (!$adressable->getSendableAdresse()) continue;
            $adresseId = $adressable->getSendableAdresse()->getId();

            if (isset($result[$adresseId])) {
                $result[$adresseId][] = $adressable;
            } else {
                $result[$adresseId] = [$adressable];
            }
        }

        return array_map(function($set) {
            /** @var AdressableInterface[] $set */
            if (count($set) === 1) return $set[0];
            else {
                return $set[0]->getSendableAdresse()->getOwner();
            }
        }, $result);
    }

    /**
     * @param AdressableInterface[] $adressables
     * @return array
     */
    public static function merge($adressables) {

        $result = [];
        foreach($adressables as $adressable) {
            if($adressable instanceof BaseFamille)
                $result[$adressable->getId()] = $adressable;

            else {

                /** @var BaseMembre $adressable */
                $id = $adressable->getFamille()->getId();
                if(!isset($result[$id]))
                    $result[$id] = [];

                $result[$id][] = $adressable;
            }
        }

        return array_map(function($item) {
            if($item instanceof BaseFamille)
                return $item;
            elseif(count($item) > 1)
                return $item[0]->getFamille();
            else
                return $item[0];
        }, $result);
    }

    /**
     * Returns the form used to configure the export
     * @return string
     */
    public function getConfigFormClass()
    {
        return EtiquettesV2Type::class;
    }

    /**
     * Returns the configuration object class
     * @return string
     */
    public function getBasicConfig()
    {
        return new EtiquettesV2Config();
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
