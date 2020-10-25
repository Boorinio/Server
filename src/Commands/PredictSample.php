<?php

namespace Rubix\Server\Commands;

use Rubix\Server\Exceptions\ValidationException;

/**
 * Predict Sample
 *
 * Predict a single sample and return the result.
 *
 * @category    Machine Learning
 * @package     Rubix/Server
 * @author      Andrew DalPino
 */
class PredictSample extends Command
{
    /**
     * The sample to predict.
     *
     * @var mixed[]
     */
    protected $sample;

    /**
     * Build the command from an associative array of data.
     *
     * @param mixed[] $data
     * @throws \Rubix\Server\Exceptions\ValidationException
     * @return self
     */
    public static function fromArray(array $data) : self
    {
        if (!isset($data['sample'])) {
            throw new ValidationException('Sample property must be present.');
        }

        return new self($data['sample']);
    }

    /**
     * @param mixed[] $sample
     * @throws \Rubix\Server\Exceptions\ValidationException
     */
    public function __construct(array $sample)
    {
        if (empty($sample)) {
            throw new ValidationException('Sample must not be empty.');
        }

        $this->sample = $sample;
    }

    /**
     * Return the sample to predict.
     *
     * @return mixed[]
     */
    public function sample() : array
    {
        return $this->sample;
    }

    /**
     * Return the message as an array.
     *
     * @return mixed[]
     */
    public function asArray() : array
    {
        return [
            'sample' => $this->sample,
        ];
    }
}
