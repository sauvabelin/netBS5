<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingsFormFactory;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ParameterController extends AbstractController
{
    #[Route('/parameters/list', name: 'netbs.core.parameters.list')]
    #[IsGranted('ROLE_ADMIN')]
    public function listParametersAction(Request $request, SettingsRegistry $registry, SettingsFormFactory $factory, ParameterManager $parameters)
    {
        $problems = [];
        foreach ($parameters->problems() as $problem)
            $problems[$problem->definition->id()] = $problem->message;

        $forms = [];
        foreach ($registry->sections() as $section => $label) {

            // Only the submitted section is handled; the others are rendered fresh.
            $submitted          = $request->isMethod('POST') && $request->request->has(SettingsFormFactory::formName($section));
            $forms[$section]    = $form = $factory->create($section, $submitted ? [] : $problems);

            if (!$submitted)
                continue;

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                foreach ($factory->submittedValues($section, $form) as [$namespace, $key, $value])
                    $parameters->setValue($namespace, $key, $value);

                $this->addFlash('success', sprintf('Paramètres « %s » enregistrés.', $label));

                return $this->redirectToRoute('netbs.core.parameters.list');
            }
        }

        return $this->render('@NetBSCore/parameters/list_parameters.html.twig', [
            'sections'  => $registry->sections(),
            'forms'     => array_map(fn($form) => $form->createView(), $forms),
            'registry'  => $registry,
        ]);
    }
}
