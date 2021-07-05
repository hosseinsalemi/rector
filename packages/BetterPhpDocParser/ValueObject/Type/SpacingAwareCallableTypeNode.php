<?php

declare (strict_types=1);
namespace Rector\BetterPhpDocParser\ValueObject\Type;

use PHPStan\PhpDocParser\Ast\NodeAttributes;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Stringable;
final class SpacingAwareCallableTypeNode extends \PHPStan\PhpDocParser\Ast\Type\CallableTypeNode
{
    use NodeAttributes;
    public function __toString() : string
    {
        // keep original (Psalm?) format, see https://github.com/rectorphp/rector/issues/2841
        return $this->createExplicitCallable();
    }
    private function createExplicitCallable() : string
    {
        /** @var IdentifierTypeNode|GenericTypeNode $returnType */
        $returnType = $this->returnType;
        $parameterTypeString = $this->createParameterTypeString();
        $returnTypeAsString = (string) $returnType;
        if (\strpos($returnTypeAsString, '|') !== \false) {
            $returnTypeAsString = '(' . $returnTypeAsString . ')';
        }
        $parameterTypeString = $this->normalizeParameterType($parameterTypeString, $returnTypeAsString);
        $returnTypeAsString = $this->normalizeReturnType($parameterTypeString, $returnTypeAsString);
        return \sprintf('%s%s%s', $this->identifier->name, $parameterTypeString, $returnTypeAsString);
    }
    private function createParameterTypeString() : string
    {
        $parameterTypeStrings = [];
        foreach ($this->parameters as $parameter) {
            $parameterTypeStrings[] = \trim((string) $parameter);
        }
        $parameterTypeString = \implode(', ', $parameterTypeStrings);
        return \trim($parameterTypeString);
    }
    /**
     * @param string $parameterTypeString
     * @param string $returnTypeAsString
     */
    private function normalizeParameterType($parameterTypeString, $returnTypeAsString) : string
    {
        if ($parameterTypeString !== '') {
            return '(' . $parameterTypeString . ')';
        }
        if ($returnTypeAsString === 'mixed') {
            return $parameterTypeString;
        }
        if ($returnTypeAsString === '') {
            return $parameterTypeString;
        }
        return '()';
    }
    /**
     * @param string $parameterTypeString
     * @param string $returnTypeAsString
     */
    private function normalizeReturnType($parameterTypeString, $returnTypeAsString) : string
    {
        if ($returnTypeAsString !== 'mixed') {
            return ':' . $returnTypeAsString;
        }
        if ($parameterTypeString !== '') {
            return ':' . $returnTypeAsString;
        }
        return '';
    }
}
