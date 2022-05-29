<?php

namespace App\Controller\Admin;

use App\Entity\ProductionCompany;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class ProductionCompanyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductionCompany::class;
    }

    /*
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextEditorField::new('description'),
        ];
    }
    */
}
