<?php

namespace App\Modules\Imports\Support;

enum ColumnKind
{
    case DateWithinPeriod;
    case Month;
    case DecimalUnsigned;
    case DecimalSigned;
    case IntegerUnsigned;
    case Text;
    case EnumValue;
    case SelectedBranchCode;
    case ClientBranchCode;
    case BranchDefinitionCode;
    case ScopeBranchOrScope;
    case ScopeOnly;
}
