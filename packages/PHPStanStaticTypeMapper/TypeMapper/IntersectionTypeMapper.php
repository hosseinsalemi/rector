<?php

declare (strict_types=1);
namespace Rector\PHPStanStaticTypeMapper\TypeMapper;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\Type;
use Rector\BetterPhpDocParser\ValueObject\Type\BracketsAwareIntersectionTypeNode;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\Php\PhpVersionProvider;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\PHPStanStaticTypeMapper\Contract\TypeMapperInterface;
use Rector\PHPStanStaticTypeMapper\Enum\TypeKind;
use Rector\PHPStanStaticTypeMapper\PHPStanStaticTypeMapper;
use RectorPrefix20220410\Symfony\Contracts\Service\Attribute\Required;
/**
 * @implements TypeMapperInterface<IntersectionType>
 */
final class IntersectionTypeMapper implements \Rector\PHPStanStaticTypeMapper\Contract\TypeMapperInterface
{
    /**
     * @var string
     */
    private const STRING = 'string';
    /**
     * @var \Rector\PHPStanStaticTypeMapper\PHPStanStaticTypeMapper
     */
    private $phpStanStaticTypeMapper;
    /**
     * @readonly
     * @var \Rector\Core\Php\PhpVersionProvider
     */
    private $phpVersionProvider;
    public function __construct(\Rector\Core\Php\PhpVersionProvider $phpVersionProvider)
    {
        $this->phpVersionProvider = $phpVersionProvider;
    }
    /**
     * @required
     */
    public function autowire(\Rector\PHPStanStaticTypeMapper\PHPStanStaticTypeMapper $phpStanStaticTypeMapper) : void
    {
        $this->phpStanStaticTypeMapper = $phpStanStaticTypeMapper;
    }
    /**
     * @return class-string<Type>
     */
    public function getNodeClass() : string
    {
        return \PHPStan\Type\IntersectionType::class;
    }
    /**
     * @param IntersectionType $type
     */
    public function mapToPHPStanPhpDocTypeNode(\PHPStan\Type\Type $type, \Rector\PHPStanStaticTypeMapper\Enum\TypeKind $typeKind) : \PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        $intersectionTypesNodes = [];
        foreach ($type->getTypes() as $intersectionedType) {
            $intersectionTypesNodes[] = $this->phpStanStaticTypeMapper->mapToPHPStanPhpDocTypeNode($intersectionedType, $typeKind);
        }
        $intersectionTypesNodes = \array_unique($intersectionTypesNodes);
        if (\count($intersectionTypesNodes) === 1) {
            return $intersectionTypesNodes[0];
        }
        return new \Rector\BetterPhpDocParser\ValueObject\Type\BracketsAwareIntersectionTypeNode($intersectionTypesNodes);
    }
    /**
     * @param IntersectionType $type
     */
    public function mapToPhpParserNode(\PHPStan\Type\Type $type, \Rector\PHPStanStaticTypeMapper\Enum\TypeKind $typeKind) : ?\PhpParser\Node
    {
        if (!$this->phpVersionProvider->isAtLeastPhpVersion(\Rector\Core\ValueObject\PhpVersionFeature::INTERSECTION_TYPES)) {
            return null;
        }
        $intersectionedTypeNodes = [];
        foreach ($type->getTypes() as $intersectionedType) {
            $resolvedType = $this->phpStanStaticTypeMapper->mapToPhpParserNode($intersectionedType, $typeKind);
            if ($intersectionedType instanceof \PHPStan\Type\Generic\GenericClassStringType) {
                $resolvedTypeName = self::STRING;
                $resolvedType = new \PhpParser\Node\Name(self::STRING);
            } elseif (!$resolvedType instanceof \PhpParser\Node\Name) {
                throw new \Rector\Core\Exception\ShouldNotHappenException();
            } else {
                $resolvedTypeName = (string) $resolvedType;
            }
            if (\in_array($resolvedTypeName, [self::STRING, 'object'], \true)) {
                return $resolvedType;
            }
            $intersectionedTypeNodes[] = $resolvedType;
        }
        return new \PhpParser\Node\IntersectionType($intersectionedTypeNodes);
    }
}
