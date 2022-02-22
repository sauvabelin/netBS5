<?php

namespace App\Exporter;

use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PDFUnite implements ExporterInterface
{
    private $config;

    private $twig;

    public function __construct(FichierConfig $config, Environment $twig)
    {
        $this->config   = $config;
        $this->twig = $twig;
    }

    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias()
    {
        return "pdf.unite";
    }

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass()
    {
        return $this->config->getGroupeClass();
    }

    /**
     * Returns a displayable name of this exporter
     * @return string
     */
    public function getName()
    {
        return "Liste d'unité";
    }

    /**
     * @param BaseGroupe[] $unites
     * @return string
     */
    public function renderView($unites)
    {
        $unite      = $unites[0];
        $sections   = [];

        /** @var BaseGroupe $groupe */
        foreach(array_merge([$unite], $unite->getEnfants()->toArray()) as $groupe)
            if($groupe->getValidity() === BaseGroupe::OUVERT)
                $sections[] = ['groupe' => $groupe, 'membres' => $this->orderSection($groupe)];

        $total      = 0;
        foreach($sections as $section)
            $total += count($section['membres']);

        return $this->twig->render('pdf/liste_unite.pdf.twig', array(
            'sections'  => $sections,
            'groupe'    => $unite,
            'total'     => $total
        ));
    }

    private function orderSection(BaseGroupe $groupe) {

        $attributions   = $groupe->getActivesAttributions();
        usort($attributions, BaseAttribution::getSortFunction());

        return array_map(function(BaseAttribution $attribution) {
            return $attribution->getMembre();
        }, $attributions);
    }

    public function getCategory()
    {
        return 'pdf';
    }

    public function export($items)
    {
        $view = $this->renderView($items);
        $pdf = new \TCPDF();
        $pdf->setCreator("netBS");
        $pdf->setAuthor("netBS");
        $pdf->setTitle("Liste d'unité");
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($view, false, false);
        $pdf->lastPage();

        // return new Response($this->renderView($items), 200);

        return new Response($pdf->Output(), 200, [
            'Content-Type' => 'application/pdf'
        ]);
    }
}