<?php

declare(strict_types=1);

namespace Rector\Naming\Naming;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Property;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PHPStan\Type\FullyQualifiedObjectType;
use Rector\StaticTypeMapper\StaticTypeMapper;

final class ExpectedNameResolver
{
    /**
     * @var NodeNameResolver
     */
    private $nodeNameResolver;

    /**
     * @var PropertyNaming
     */
    private $propertyNaming;

    /**
     * @var StaticTypeMapper
     */
    private $staticTypeMapper;

    /**
     * @var BetterNodeFinder
     */
    private $betterNodeFinder;

    public function __construct(
        NodeNameResolver $nodeNameResolver,
        PropertyNaming $propertyNaming,
        StaticTypeMapper $staticTypeMapper,
        BetterNodeFinder $betterNodeFinder
    ) {
        $this->nodeNameResolver = $nodeNameResolver;
        $this->propertyNaming = $propertyNaming;
        $this->staticTypeMapper = $staticTypeMapper;
        $this->betterNodeFinder = $betterNodeFinder;
    }

    public function resolveForPropertyIfNotYet(Property $property): ?string
    {
        $expectedName = $this->resolveForProperty($property);
        if ($expectedName === null) {
            return null;
        }

        /** @var string $propertyName */
        $propertyName = $this->nodeNameResolver->getName($property);
        if ($this->endsWith($propertyName, $expectedName)) {
            return null;
        }

        if ($this->nodeNameResolver->isName($property, $expectedName)) {
            return null;
        }

        return $expectedName;
    }

    public function resolveForParamIfNotYet(Param $param): ?string
    {
        $expectedName = $this->resolveForParam($param);
        if ($expectedName === null) {
            return null;
        }

        /** @var string $currentName */
        $currentName = $this->nodeNameResolver->getName($param->var);
        if ($currentName === $expectedName) {
            return null;
        }

        if ($this->endsWith($currentName, $expectedName)) {
            return null;
        }

        return $expectedName;
    }

    public function resolveForParam(Param $param): ?string
    {
        // nothing to verify
        if ($param->type === null) {
            return null;
        }

        $staticType = $this->staticTypeMapper->mapPhpParserNodePHPStanType($param->type);
        return $this->propertyNaming->getExpectedNameFromType($staticType);
    }

    public function resolveForProperty(Property $property): ?string
    {
        /** @var PhpDocInfo|null $phpDocInfo */
        $phpDocInfo = $property->getAttribute(AttributeKey::PHP_DOC_INFO);
        if ($phpDocInfo === null) {
            return null;
        }

        return $this->propertyNaming->getExpectedNameFromType($phpDocInfo->getVarType());
    }

    public function resolveForAssignNonNew(Assign $assign): ?string
    {
        if ($assign->expr instanceof New_) {
            return null;
        }

        if (! $assign->var instanceof Variable) {
            return null;
        }

        /** @var Variable $variable */
        $variable = $assign->var;

        return $this->nodeNameResolver->getName($variable);
    }

    public function resolveForAssignNew(Assign $assign): ?string
    {
        if (! $assign->expr instanceof New_) {
            return null;
        }

        if (! $assign->var instanceof Variable) {
            return null;
        }

        /** @var New_ $new */
        $new = $assign->expr;
        if (! $new->class instanceof Name) {
            return null;
        }

        $className = $this->nodeNameResolver->getName($new->class);
        if ($className === null) {
            return null;
        }

        $fullyQualifiedObjectType = new FullyQualifiedObjectType($className);

        return $this->propertyNaming->getExpectedNameFromType($fullyQualifiedObjectType);
    }

    public function resolveForGetCallExpr(Expr $expr): ?string
    {
        /** @var MethodCall|StaticCall|FuncCall|null $callNode */
        $callNode = $this->betterNodeFinder->findFirst($expr, function (Node $node) {
            return $node instanceof MethodCall || $node instanceof StaticCall || $node instanceof FuncCall;
        });

        if ($callNode === null) {
            return null;
        }

        $name = $this->nodeNameResolver->getName($callNode->name);
        if ($name === null) {
            return null;
        }

        // @see https://regex101.com/r/hnU5pm/2/
        $matches = Strings::match($name, '#^get(([A-Z]).+)#');

        if ($matches === null) {
            return null;
        }

        return lcfirst($matches[1]);
    }

    /**
     * Ends with ucname
     * Starts with adjective, e.g. (Post $firstPost, Post $secondPost)
     */
    private function endsWith(string $currentName, string $expectedName): bool
    {
        $suffixNamePattern = '#\w+' . ucfirst($expectedName) . '#';
        return (bool) Strings::match($currentName, $suffixNamePattern);
    }
}
