<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\Rule\RuleDescriber;
use NetBS\CoreBundle\Rule\RuleRegistry;
use NetBS\CoreBundle\Rule\RuleValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RuleController extends AbstractController
{
    /** Live sentence and errors for the rule editor. Body: {context, rule (object or JSON string)}. */
    #[Route('/rules/describe', name: 'netbs.core.rule.describe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function describeAction(Request $request, RuleRegistry $registry, RuleDescriber $describer, RuleValidator $validator): JsonResponse
    {
        $body    = json_decode($request->getContent(), true);
        $context = is_array($body) && is_string($body['context'] ?? null) ? $body['context'] : '';

        try {
            $registry->context($context);
        } catch (\LogicException) {
            return new JsonResponse(['error' => 'Contexte inconnu.'], 400);
        }

        $rule = $body['rule'] ?? null;
        $json = is_array($rule) ? json_encode($rule, JSON_UNESCAPED_UNICODE) : (is_string($rule) ? $rule : null);

        return new JsonResponse([
            'sentence' => $describer->describe($json, $context),
            'errors'   => $validator->validate($json, $context),
        ]);
    }
}
