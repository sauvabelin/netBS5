<?php

namespace App\Exporter;

use App\Model\EtiquettesStammConfig;
use NetBS\FichierBundle\Exporter\Config\EtiquettesV2Config;
use NetBS\FichierBundle\Exporter\PDFEtiquettesV2;

class EtiquettesV2Exporter extends PDFEtiquettesV2
{
    public function getBasicConfig()
    {
        return [
            new EtiquettesV2Config(),
            new EtiquettesStammConfig(),
        ];
    }
}
