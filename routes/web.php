<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ConsignmentController;
use App\Http\Controllers\CutDeclarationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoDataController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FabricInspectionController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\LabReportController;
use App\Http\Controllers\MarkerController;
use App\Http\Controllers\MaterialIssueController;
use App\Http\Controllers\RejectionController;
use App\Http\Controllers\MasterData\AccessoryController;
use App\Http\Controllers\MasterData\ColorController;
use App\Http\Controllers\MasterData\FabricTypeController;
use App\Http\Controllers\MasterData\FactoryController;
use App\Http\Controllers\MasterData\ProductModelController;
use App\Http\Controllers\MasterData\SizeController;
use App\Http\Controllers\MasterData\SupplierController;
use App\Http\Controllers\MasterData\WarehouseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProductionReceiptController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockAdditionController;
use App\Http\Controllers\WorkOrderController;
use App\Support\CrudRoutes;
use Illuminate\Support\Facades\Route;

// ── الدخول ───────────────────────────────────────────────────────
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

Route::middleware('auth.user')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ── البيانات الأساسية ────────────────────────────────────────
    Route::prefix('master')->group(function () {
        CrudRoutes::make('suppliers',      SupplierController::class);
        CrudRoutes::make('factories',      FactoryController::class);
        CrudRoutes::make('warehouses',     WarehouseController::class);
        CrudRoutes::make('fabric-types',   FabricTypeController::class);
        CrudRoutes::make('sizes',          SizeController::class);
        CrudRoutes::make('accessories',    AccessoryController::class);
        CrudRoutes::make('product-models', ProductModelController::class);

        // مقاسات وإكسسوارات الموديل
        Route::get('product-models/{id}/sizes', [ProductModelController::class, 'sizes'])
            ->middleware('can.do:master.view')->name('product-models.sizes');
        Route::middleware('can.do:master.manage')->group(function () {
            Route::post('product-models/{id}/sizes',         [ProductModelController::class, 'saveSizes'])->name('product-models.sizes.save');
            Route::post('product-models/{id}/bom',           [ProductModelController::class, 'addBom'])->name('product-models.bom.add');
            Route::delete('product-models/{id}/bom/{bomId}', [ProductModelController::class, 'deleteBom'])->name('product-models.bom.delete');
        });

        // الألوان — مفيش حذف، دمج وإيقاف بس
        Route::get('colors', [ColorController::class, 'index'])
            ->middleware('can.do:master.view')->name('colors.index');
        Route::middleware('can.do:master.manage')->group(function () {
            Route::post('colors',             [ColorController::class, 'store'])->name('colors.store');
            Route::put('colors/{id}',         [ColorController::class, 'update'])->name('colors.update');
            Route::post('colors/{id}/toggle', [ColorController::class, 'toggleStatus'])->name('colors.toggle');
        });
        Route::post('colors/merge', [ColorController::class, 'merge'])
            ->middleware('can.do:colors.merge')->name('colors.merge');
    });

    /* ── دورة طلب الشراء ──────────────────────────────────────────
     | مستند واحد بيمر على تلات أيادي، وكل يد ليها صلاحيتها:
     |   po.request  التخطيط    ينشئ ويعدّل الأصناف
     |   po.source   المشتريات  تحدد المورد والأسعار والتوريد
     |   po.finance  الحسابات   تعلم بالمستحق
    */
    Route::middleware('can.do:po.view,po.request,po.source,po.finance')->group(function () {
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::get('purchase-orders/{purchase_order}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
    });

    Route::middleware('can.do:po.request')->group(function () {
        Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders',       [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::put('purchase-orders/{purchase_order}',    [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::delete('purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');
        Route::post('purchase-orders/{purchase_order}/to-purchasing', [PurchaseOrderController::class, 'toPurchasing'])->name('purchase-orders.to-purchasing');
    });

    Route::middleware('can.do:po.source')->group(function () {
        Route::post('purchase-orders/{purchase_order}/sourcing',   [PurchaseOrderController::class, 'saveSourcing'])->name('purchase-orders.sourcing');
        Route::post('purchase-orders/{purchase_order}/to-finance', [PurchaseOrderController::class, 'toFinance'])->name('purchase-orders.to-finance');
    });

    Route::middleware('can.do:po.finance')->group(function () {
        Route::post('purchase-orders/{purchase_order}/finance-ack', [PurchaseOrderController::class, 'financeAck'])->name('purchase-orders.finance-ack');
        Route::get('finance/payables', [FinanceController::class, 'payables'])->name('finance.payables');
    });

    // ── أذون المخازن والأحواض ────────────────────────────────────
    Route::middleware('can.do:receipt.view')->group(function () {
        Route::get('goods-receipts',  [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::get('stock-additions', [StockAdditionController::class, 'index'])->name('stock-additions.index');
        Route::get('goods-receipts/{goods_receipt}/print',   [GoodsReceiptController::class, 'print'])->name('goods-receipts.print');
        Route::get('stock-additions/{stock_addition}/print', [StockAdditionController::class, 'print'])->name('stock-additions.print');
        Route::get('consignments',               [ConsignmentController::class, 'index'])->name('consignments.index');
        Route::get('consignments/{consignment}', [ConsignmentController::class, 'show'])->name('consignments.show');
    });

    Route::middleware('can.do:receipt.manage')->group(function () {
        Route::resource('goods-receipts',  GoodsReceiptController::class)->except(['show', 'index']);
        Route::resource('stock-additions', StockAdditionController::class)->except(['show', 'index']);
        Route::post('goods-receipts/{goods_receipt}/submit',   [GoodsReceiptController::class, 'submit'])->name('goods-receipts.submit');
        Route::post('stock-additions/{stock_addition}/submit', [StockAdditionController::class, 'submit'])->name('stock-additions.submit');
        Route::put('consignments/{consignment}', [ConsignmentController::class, 'update'])->name('consignments.update');
    });

    // ── الفحص والمعمل ────────────────────────────────────────────
    Route::middleware('can.do:qc.view')->group(function () {
        Route::get('inspections', [FabricInspectionController::class, 'index'])->name('inspections.index');
        Route::get('lab-reports', [LabReportController::class, 'index'])->name('lab-reports.index');
        Route::get('inspections/{inspection}/print', [FabricInspectionController::class, 'print'])->name('inspections.print');
        Route::get('lab-reports/{lab_report}/print', [LabReportController::class, 'print'])->name('lab-reports.print');
    });

    Route::middleware('can.do:qc.manage')->group(function () {
        Route::resource('inspections', FabricInspectionController::class)
            ->parameters(['inspections' => 'inspection'])->except(['show', 'index']);
        Route::resource('lab-reports', LabReportController::class)->except(['show', 'index']);
        Route::post('inspections/{inspection}/submit', [FabricInspectionController::class, 'submit'])->name('inspections.submit');
        Route::post('lab-reports/{lab_report}/submit', [LabReportController::class, 'submit'])->name('lab-reports.submit');
    });

    // ── الماركرات ────────────────────────────────────────────────
    Route::middleware('can.do:marker.view')->group(function () {
        Route::get('marker-requests',        [MarkerController::class, 'requests'])->name('markers.requests');
        Route::get('marker-requests/create', [MarkerController::class, 'createRequest'])->name('markers.requests.create');
        Route::post('marker-requests',       [MarkerController::class, 'storeRequest'])->name('markers.requests.store');
        Route::get('markers',                [MarkerController::class, 'index'])->name('markers.index');
    });

    Route::middleware('can.do:marker.manage')->group(function () {
        Route::resource('markers', MarkerController::class)->except(['show', 'index']);
        Route::post('markers/{marker}/submit', [MarkerController::class, 'submit'])->name('markers.submit');
    });

    // ── أوامر الشغل ──────────────────────────────────────────────
    Route::middleware('can.do:wo.view')->group(function () {
        Route::get('work-orders',                    [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('work-orders/{work_order}/print', [WorkOrderController::class, 'print'])->name('work-orders.print');
    });

    Route::middleware('can.do:wo.manage')->group(function () {
        Route::get('work-orders/create',              [WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::post('work-orders',                    [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::get('work-orders/{work_order}/edit',   [WorkOrderController::class, 'edit'])->name('work-orders.edit');
        Route::put('work-orders/{work_order}',        [WorkOrderController::class, 'update'])->name('work-orders.update');
        Route::delete('work-orders/{work_order}',     [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');
        Route::post('work-orders/{work_order}/submit',[WorkOrderController::class, 'submit'])->name('work-orders.submit');
        Route::post('work-orders/{work_order}/send',  [WorkOrderController::class, 'sendToFactory'])->name('work-orders.send');
    });

    Route::post('work-orders/{work_order}/close', [WorkOrderController::class, 'close'])
        ->middleware('can.do:wo.close')->name('work-orders.close');

    Route::post('work-orders-calc', [WorkOrderController::class, 'calc'])
        ->middleware('can.do:wo.view,wo.manage,marker.manage')->name('work-orders.calc');

    // لازم تفضل آخر واحدة عشان ما تخطفش الراوتس اللي فوقها
    Route::get('work-orders/{work_order}', [WorkOrderController::class, 'show'])
        ->middleware('can.do:wo.view')->name('work-orders.show');

    // ── إذن صرف خام ──────────────────────────────────────────────
    Route::get('material-issues', [MaterialIssueController::class, 'index'])
        ->middleware('can.do:receipt.view,wo.view')->name('material-issues.index');
    Route::get('material-issues/{material_issue}/print', [MaterialIssueController::class, 'print'])
        ->middleware('can.do:receipt.view,wo.view')->name('material-issues.print');

    Route::middleware('can.do:receipt.manage')->group(function () {
        Route::resource('material-issues', MaterialIssueController::class)->except(['show', 'index']);
        Route::post('material-issues/{material_issue}/submit', [MaterialIssueController::class, 'submit'])
            ->name('material-issues.submit');
    });

    // ── المرفوضات والمعلّق ───────────────────────────────────────
    Route::get('rejections', [RejectionController::class, 'index'])
        ->middleware('can.do:qc.view,receipt.view,po.view')->name('rejections.index');
    Route::post('goods-receipts/{goods_receipt}/rejections', [RejectionController::class, 'store'])
        ->middleware('can.do:qc.manage,receipt.manage')->name('rejections.store');
    Route::post('rejections/{rejection}/resolve', [RejectionController::class, 'resolve'])
        ->middleware('can.do:wo.manage,po.source')->name('rejections.resolve');
    Route::delete('rejections/{rejection}', [RejectionController::class, 'destroy'])
        ->middleware('can.do:qc.manage,receipt.manage')->name('rejections.destroy');

    // ── بيان القص والاستلام ──────────────────────────────────────
    Route::get('cut-declarations', [CutDeclarationController::class, 'index'])
        ->middleware('can.do:cut.view')->name('cut-declarations.index');

    Route::middleware('can.do:cut.manage')->group(function () {
        Route::resource('cut-declarations', CutDeclarationController::class)->except(['show', 'index']);
        Route::post('cut-declarations/{cut_declaration}/submit', [CutDeclarationController::class, 'submit'])->name('cut-declarations.submit');
    });

    Route::middleware('can.do:prod.manage,wo.view')->group(function () {
        Route::resource('production-receipts', ProductionReceiptController::class)->except(['show']);
        Route::post('production-receipts/{production_receipt}/submit', [ProductionReceiptController::class, 'submit'])->name('production-receipts.submit');
    });

    // ── التخطيط والفوركاست ───────────────────────────────────────
    // الحاسبة مفتوحة لأي حد بيشتغل على الأرقام
    Route::get('calculator', [PlanningController::class, 'calculator'])->name('planning.calculator');

    Route::middleware('can.do:forecast.view')->group(function () {
        Route::get('coverage',     [PlanningController::class, 'coverage'])->name('planning.coverage');
        Route::get('color-ratios', [PlanningController::class, 'colorRatios'])->name('planning.color-ratios');
        Route::get('forecast',     [PlanningController::class, 'forecast'])->name('planning.forecast');
        Route::get('safety-stock', [PlanningController::class, 'safetyStock'])->name('planning.safety-stock');
    });

    Route::middleware('can.do:forecast.manage')->group(function () {
        Route::post('color-ratios',      [PlanningController::class, 'saveColorRatios'])->name('planning.color-ratios.save');
        Route::post('forecast/generate', [PlanningController::class, 'generateForecast'])->name('planning.forecast.generate');
        Route::post('forecast/sync',     [PlanningController::class, 'syncActuals'])->name('planning.forecast.sync');
        Route::post('safety-stock',      [PlanningController::class, 'saveSafetyStock'])->name('planning.safety-stock.save');
    });

    // ── الاعتمادات ───────────────────────────────────────────────
    Route::get('approvals',                    [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/{approval}/reject',  [ApprovalController::class, 'reject'])->name('approvals.reject');

    // ── نقاش المستندات ───────────────────────────────────────────
    Route::post('comments/{type}/{id}', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}',  [CommentController::class, 'destroy'])->name('comments.destroy');

    // ── الإشعارات ────────────────────────────────────────────────
    Route::get('notifications',            [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all',  [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // ── استيراد وتصدير ───────────────────────────────────────────
    Route::middleware('can.do:import.manage')->group(function () {
    Route::get('import-export',            [ImportExportController::class, 'index'])->name('io.index');
    Route::post('import/colors',           [ImportExportController::class, 'importColors'])->name('io.import.colors');
    Route::post('import/sales',            [ImportExportController::class, 'importSales'])->name('io.import.sales');
    Route::post('import/stock',            [ImportExportController::class, 'importStock'])->name('io.import.stock');
    Route::get('export/colors',            [ImportExportController::class, 'exportColors'])->name('io.export.colors');
    Route::get('export/consignments',      [ImportExportController::class, 'exportConsignments'])->name('io.export.consignments');
    Route::get('export/work-orders',       [ImportExportController::class, 'exportWorkOrders'])->name('io.export.work-orders');
    Route::get('export/coverage',          [ImportExportController::class, 'exportCoverage'])->name('io.export.coverage');
    Route::get('template/{type}',          [ImportExportController::class, 'template'])->name('io.template');
    });

    // ── الإعدادات ────────────────────────────────────────────────
    Route::prefix('settings')->group(function () {
        Route::middleware('can.do:settings.users')->group(function () {
            Route::get('users',        [SettingsController::class, 'users'])->name('settings.users');
            Route::post('users',       [SettingsController::class, 'storeUser'])->name('settings.users.store');
            Route::put('users/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
        });

        Route::middleware('can.do:settings.roles')->group(function () {
            Route::get('roles', [SettingsController::class, 'roles'])->name('settings.roles');
            Route::post('roles/{role}/permissions', [SettingsController::class, 'saveRolePermissions'])->name('settings.roles.permissions');
        });

        Route::middleware('can.do:settings.flows')->group(function () {
            Route::get('approval-flows',                        [SettingsController::class, 'approvalFlows'])->name('settings.flows');
            Route::post('approval-flows/{flow}/steps',          [SettingsController::class, 'addFlowStep'])->name('settings.flows.step.add');
            Route::delete('approval-flows/{flow}/steps/{step}', [SettingsController::class, 'deleteFlowStep'])->name('settings.flows.step.delete');
            Route::post('approval-flows/{flow}/toggle',         [SettingsController::class, 'toggleFlow'])->name('settings.flows.toggle');
        });

        // أدوات الداتا — للأدمن بس
        Route::middleware('can.do:settings.data')->group(function () {
            Route::get('data',           [DemoDataController::class, 'index'])->name('settings.data');
            Route::post('data/paper',    [DemoDataController::class, 'generatePaper'])->name('data.paper');
            Route::post('data/generate', [DemoDataController::class, 'generate'])->name('data.generate');
            Route::post('data/master',   [DemoDataController::class, 'generateMaster'])->name('data.master');
            Route::post('data/reset',    [DemoDataController::class, 'reset'])->name('data.reset');
        });

        Route::get('activity', [SettingsController::class, 'activity'])
            ->middleware('can.do:settings.audit')->name('settings.activity');
    });
});
