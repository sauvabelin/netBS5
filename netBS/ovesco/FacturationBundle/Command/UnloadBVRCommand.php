<?php

namespace Ovesco\FacturationBundle\Command;

use Genkgo\Camt\Config;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UnloadBVRCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ovesco:facturation:unload-bvr')
            ->setDescription('Hello PhpStorm');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parsedBVR = null;
        $this->parseBVRFile(__DIR__ . "/20191008_CAMT.xml");
    }

    private function parseBVRFile($file) {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $reader = new \Genkgo\Camt\Reader(Config::getDefault());
        $data = $reader->readFile($file);
        $statements = $data->getRecords();

        foreach($statements as $statement) {
            foreach($statement->getEntries() as $entry) {

                foreach ($entry->getTransactionDetails() as $transactionDetail) {

                    $paiements = $em->getRepository('OvescoFacturationBundle:Paiement')->findBy(['transactionDetails' => serialize($transactionDetail)]);
                    foreach ($paiements as $paiement) {
                        /** @var Facture $facture */
                        $facture = $paiement->getFacture();
                        $facture->removePaiement($paiement);
                        $em->remove($paiement);
                        echo "Removing paiement from facture...\n";
                    }
                }
            }
        }
        $em->flush();
    }
}
