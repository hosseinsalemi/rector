<?php

declare (strict_types=1);
namespace Rector\TypeDeclaration\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\NeverType;
use PHPStan\Type\VoidType;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTypeChanger;
use Rector\Core\Contract\Rector\AllowEmptyConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\TypeDeclaration\TypeInferer\SilentVoidResolver;
use Rector\VendorLocker\NodeVendorLocker\ClassMethodReturnVendorLockResolver;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use RectorPrefix20220410\Webmozart\Assert\Assert;
/**
 * @see \Rector\Tests\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector\AddVoidReturnTypeWhereNoReturnRectorTest
 */
final class AddVoidReturnTypeWhereNoReturnRector extends \Rector\Core\Rector\AbstractRector implements \Rector\VersionBonding\Contract\MinPhpVersionInterface, \Rector\Core\Contract\Rector\AllowEmptyConfigurableRectorInterface
{
    /**
     * @var string using phpdoc instead of a native void type can ease the migration path for consumers of code being processed.
     */
    public const USE_PHPDOC = 'use_phpdoc';
    /**
     * @var bool
     */
    private $usePhpdoc = \false;
    /**
     * @readonly
     * @var \Rector\TypeDeclaration\TypeInferer\SilentVoidResolver
     */
    private $silentVoidResolver;
    /**
     * @readonly
     * @var \Rector\VendorLocker\NodeVendorLocker\ClassMethodReturnVendorLockResolver
     */
    private $classMethodReturnVendorLockResolver;
    /**
     * @readonly
     * @var \Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTypeChanger
     */
    private $phpDocTypeChanger;
    public function __construct(\Rector\TypeDeclaration\TypeInferer\SilentVoidResolver $silentVoidResolver, \Rector\VendorLocker\NodeVendorLocker\ClassMethodReturnVendorLockResolver $classMethodReturnVendorLockResolver, \Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTypeChanger $phpDocTypeChanger)
    {
        $this->silentVoidResolver = $silentVoidResolver;
        $this->classMethodReturnVendorLockResolver = $classMethodReturnVendorLockResolver;
        $this->phpDocTypeChanger = $phpDocTypeChanger;
    }
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Add return type void to function like without any return', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample(<<<'CODE_SAMPLE'
final class SomeClass
{
    public function getValues()
    {
        $value = 1000;
        return;
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
final class SomeClass
{
    public function getValues(): void
    {
        $value = 1000;
        return;
    }
}
CODE_SAMPLE
, [self::USE_PHPDOC => \false])]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Stmt\ClassMethod::class, \PhpParser\Node\Stmt\Function_::class, \PhpParser\Node\Expr\Closure::class];
    }
    /**
     * @param ClassMethod|Function_|Closure $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        if ($node->returnType !== null) {
            return null;
        }
        if ($this->shouldSkipClassMethod($node)) {
            return null;
        }
        if (!$this->silentVoidResolver->hasExclusiveVoid($node)) {
            return null;
        }
        if ($this->usePhpdoc) {
            $this->changePhpDocToVoidIfNotNever($node);
            return $node;
        }
        if ($node instanceof \PhpParser\Node\Stmt\ClassMethod && $this->classMethodReturnVendorLockResolver->isVendorLocked($node)) {
            return null;
        }
        $node->returnType = new \PhpParser\Node\Identifier('void');
        return $node;
    }
    public function provideMinPhpVersion() : int
    {
        return \Rector\Core\ValueObject\PhpVersionFeature::VOID_TYPE;
    }
    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration) : void
    {
        $usePhpdoc = $configuration[self::USE_PHPDOC] ?? (bool) \current($configuration);
        \RectorPrefix20220410\Webmozart\Assert\Assert::boolean($usePhpdoc);
        $this->usePhpdoc = $usePhpdoc;
    }
    /**
     * @param \PhpParser\Node|\PhpParser\Node\Expr\Closure|\PhpParser\Node\Stmt\ClassMethod|\PhpParser\Node\Stmt\Function_ $node
     */
    private function changePhpDocToVoidIfNotNever($node) : void
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);
        if ($phpDocInfo->getReturnType() instanceof \PHPStan\Type\NeverType) {
            return;
        }
        $this->phpDocTypeChanger->changeReturnType($phpDocInfo, new \PHPStan\Type\VoidType());
    }
    /**
     * @param \PhpParser\Node\Expr\Closure|\PhpParser\Node\Stmt\ClassMethod|\PhpParser\Node\Stmt\Function_ $functionLike
     */
    private function shouldSkipClassMethod($functionLike) : bool
    {
        if (!$functionLike instanceof \PhpParser\Node\Stmt\ClassMethod) {
            return \false;
        }
        if ($functionLike->isMagic()) {
            return \true;
        }
        if ($functionLike->isAbstract()) {
            return \true;
        }
        if ($functionLike->isProtected()) {
            return !$this->isInsideFinalClass($functionLike);
        }
        return \false;
    }
    private function isInsideFinalClass(\PhpParser\Node\Stmt\ClassMethod $classMethod) : bool
    {
        $scope = $classMethod->getAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::SCOPE);
        if (!$scope instanceof \PHPStan\Analyser\Scope) {
            return \false;
        }
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof \PHPStan\Reflection\ClassReflection) {
            return \false;
        }
        return $classReflection->isFinal();
    }
}
