<?php

namespace Rubix\Server\Payloads;

/**
 * Predict Sample Payload
 *
 * This is the response returned from a predict sample command containing
 * the prediction returned from the model.
 *
 * @category    Machine Learning
 * @package     Rubix/Server
 * @author      Andrew DalPino
 */
class PredictSamplePayload extends Payload
{
    /**
     * The prediction returned from the model.
     *
     * @var mixed
     */
    protected $prediction;

    /**
     * Build the response from an associative array of data.
     *
     * @param mixed[] $data
     * @return self
     */
    public static function fromArray(array $data) : self
    {
        return new self($data['prediction'] ?? []);
    }

    /**
     * @param mixed $prediction
     */
    public function __construct($prediction)
    {
        $this->prediction = $prediction;
    }

    /**
     * Return the prediction.
     *
     * @return mixed
     */
    public function prediction()
    {
        return $this->prediction;
    }

    /**
     * Return the message as an array.
     *
     * @return mixed[]
     */
    public function asArray() : array
    {
        return [
            'prediction' => $this->prediction,
        ];
    }
}