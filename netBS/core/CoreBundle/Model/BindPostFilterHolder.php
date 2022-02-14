<?php

namespace NetBS\CoreBundle\Model;

class BindPostFilterHolder
{
    private $binders = [];

    public function addBinder(BinderInterface $binder, $data, array $options = [])
    {
        $this->binders[] = [
            'binder' => $binder,
            'data' => $data,
            'options' => $options
        ];
    }

    /**
     * @return array
     */
    public function getBinders()
    {
        return $this->binders;
    }
}
