<?php

namespace NetBS\CoreBundle\Model;

use Symfony\Component\HttpFoundation\Request;

class XEditable
{
    /**
     * @var string
     */
    protected $id;

    /**
     * Le champ concerné
     * @var string
     */
    protected $field;

    /**
     * le réservoir de données
     * @var array
     */
    protected $data = [];

    /**
     * La valeur de base
     * @var mixed
     */
    protected $baseValue;

    /**
     * Le formType de base
     * @var string
     */
    protected $baseType;

    public function __construct(Request $request)
    {
        $this->id           = $request->get('pk');
        $this->baseValue    = $request->get('value');
        $this->data         = json_decode($request->get('name'), true);
        $this->field        = $this->data['field'];
        $this->baseType     = $this->data['type'];
    }

    public function getFinalValue() {

        if(isset($this->data['multiple']) && !in_array($this->data['multiple'], ['', 'false', false]))
            return $this->formatMultipleValue();

        if($this->getNormalizedType() === 'checkbox' && is_array($this->baseValue) && isset($this->baseValue[0]))
            return $this->baseValue[0];

        return $this->baseValue;
    }

    public function formatMultipleValue($delimiter = ',') {

        return is_array($this->baseValue) ? $this->baseValue : explode($delimiter, $this->baseValue);
    }

    public function getNormalizedType() {

        $type = $this->baseType;

        if(in_array($type, ['select2_document']))
            return 'document';

        if(in_array($type, ['select2', 'choice']))
            return 'choice';

        if(in_array($type, ['switch']))
            return 'checkbox';

        return $type;
    }

    public function getTypeOptions() {

        $multiple   = $this->data['multiple'] == 'true' ? true : false;

        if($this->getNormalizedType() == 'document')
            return [
                'class'     => base64_decode($this->data['class']),
                'multiple'  => $multiple
            ];

        if($this->baseType == 'ajax_select2_document')
            return [
                'class'         => base64_decode($this->data['class']),
                'multiple'      => $multiple
            ];

        if(in_array($this->getNormalizedType(), ['choice']))
            return [
                'choices'   => $this->formatMultipleValue(),
                'multiple'  => $multiple
            ];


        return [];
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getData($key)
    {
        return $this->data[$key];
    }

    /**
     * @return mixed
     */
    public function getBaseValue()
    {
        return $this->baseValue;
    }

    /**
     * @return string
     */
    public function getBaseType()
    {
        return $this->baseType;
    }
}