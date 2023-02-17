<?php

namespace NetBS\FichierBundle\Exporter;

use NetBS\CoreBundle\Exporter\CSVColumns;
use NetBS\CoreBundle\Exporter\CSVExporter;
use NetBS\CoreBundle\Utils\StrUtil;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Mapping\Personne;
use NetBS\FichierBundle\Service\FichierConfig;
use App\Import\Model\WNGHelper;

class CSVRega extends CSVExporter
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    private static function convert($ville) {

        $villes = [
            "Renens" => "Renens VD",
            "Cheseaux/Lausanne" => "Cheseaux-Lausanne",
            "Bussigny" => "Bussigny-Lausanne",
            "Chappelle-sur-Moudon" => "Chapelle-s-Moudon",
            "Le Mont" => "Mont-sur-Lausanne",
            "Cossonay" => "Cossonay-Ville",
            "Romanel" => "Romanel-s-Lausanne",
            "Cheseaux" => "Cheseaux-Lausanne",
            "Saint-Légier" => "St-Légier-Chiésaz",
            "Cheseaux sur Lausanne" => "Cheseaux-Lausanne",
            "Mont sur Lausanne" => "Mont-sur-Lausanne",
            "Le Mont sur Lausanne" => "Mont-sur-Lausanne",
            "Le Mont-sur-Lausanne" => "Mont-sur-Lausanne",
            "Lausanne-26" => "Lausanne 26",
            "Cheseaux-sur-Lausanne" => "Cheseaux-Lausanne",
            "Bretigny-sur-Morrens" => "Bretigny-Morrens",
            "Fiaugères" => "St-Martin FR"
        ];

        foreach($villes as $key => $result) {
            similar_text($key, $ville, $perc);
            if ($perc > 92) return $result;
        }

        return $ville;
    }

    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias()
    {
        return 'csv.rega';
    }

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass()
    {
        return $this->config->getMembreClass();
    }

    /**
     * Returns a displayable name of this exporter
     * @return string
     */
    public function getName()
    {
        return "Liste rega";
    }

    /**
     * @param CSVColumns $columns
     */
    public function configureColumns(CSVColumns $columns)
    {
        $columns
            ->addColumn('NO_PERS_BDNJS', function(BaseMembre $membre) {
                return null;
            })
            ->addColumn('NOM', function(BaseMembre $membre) {
                return StrUtil::removeAccents($membre->getFamille()->getNom());
            })
            ->addColumn('PRENOM', function (BaseMembre $membre) {
                return StrUtil::removeAccents($membre->getPrenom());
            })
            ->addColumn('DAT_NAISSANCE', function(BaseMembre $membre) {
                return $membre->getNaissance()->format('d.m.Y');
            })
            ->addColumn('SEXE', function (BaseMembre $membre) {
                return $membre->getSexe() === Personne::FEMME ? '2' : '1';
            })
            ->addColumn('N_AVS', function(BaseMembre $membre) {
                return $membre->getNumeroAvsRega();
            })
            ->addColumn('PEID', function(BaseMembre $membre) {
                return null;
            })
            ->addColumn('NATIONALITE', function(BaseMembre $membre) {
                return 'CH';
            })
            ->addColumn('1ERE_LANGUE', function (BaseMembre $membre) {
                return 'F';
            })
            ->addColumn('RUE', function(BaseMembre $membre) {
                if($adresse = $membre->getSendableAdresse())
                    return StrUtil::removeAccents($adresse->getRue());
            })
            ->addColumn('NUMERO', function(BaseMembre $membre) {
                return null;
            })
            ->addColumn('NPA', function(BaseMembre $membre) {
                if($adresse = $membre->getSendableAdresse())
                    return $adresse->getNpa();
            })
            ->addColumn('LOCALITE', function(BaseMembre $membre) {
                if($adresse = $membre->getSendableAdresse())
                    return StrUtil::removeAccents(self::convert($adresse->getLocalite()));
            })
            ->addColumn('PAYS', function(BaseMembre $membre) {
                if($adresse = $membre->getSendableAdresse())
                    return $adresse->getPays() === "CH" ? "CH" : "DIV";
                return "CH";
            })
        ;
    }
}
