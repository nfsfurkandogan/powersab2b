/*
  Powersa B2B Logo read-only preflight for SSMS

  Bu script sadece SELECT yapar. UPDATE/INSERT/DELETE yoktur.
  SSMS'te LOGODB seciliyken calistirin.
*/

USE [LOGODB];
SET NOCOUNT ON;

DECLARE @FirmNo NVARCHAR(3) = N'003';
DECLARE @PeriodNo NVARCHAR(2) = N'01';
DECLARE @SampleSku NVARCHAR(64) = N'0451103033';

PRINT '== Powersa B2B Logo read preflight ==';
SELECT
    @@SERVERNAME AS server_name,
    DB_NAME() AS database_name,
    @FirmNo AS firm_no,
    @PeriodNo AS period_no,
    SYSDATETIME() AS checked_at;

PRINT '== Core tables existence and row counts ==';
DECLARE @Tables TABLE (
    label NVARCHAR(64) NOT NULL,
    table_name SYSNAME NOT NULL,
    full_name NVARCHAR(256) NOT NULL
);

INSERT INTO @Tables (label, table_name, full_name)
VALUES
    (N'customers', N'LG_003_CLCARD', N'dbo.LG_003_CLCARD'),
    (N'products', N'LG_003_ITEMS', N'dbo.LG_003_ITEMS'),
    (N'prices', N'LG_003_PRCLIST', N'dbo.LG_003_PRCLIST'),
    (N'stock_totals', N'LV_003_01_STINVTOT', N'dbo.LV_003_01_STINVTOT'),
    (N'stock_fiches', N'LG_003_01_STFICHE', N'dbo.LG_003_01_STFICHE'),
    (N'stock_lines', N'LG_003_01_STLINE', N'dbo.LG_003_01_STLINE'),
    (N'orders', N'LG_003_01_ORFICHE', N'dbo.LG_003_01_ORFICHE'),
    (N'order_lines', N'LG_003_01_ORFLINE', N'dbo.LG_003_01_ORFLINE'),
    (N'invoices', N'LG_003_01_INVOICE', N'dbo.LG_003_01_INVOICE'),
    (N'cash_lines', N'LG_003_01_KSLINES', N'dbo.LG_003_01_KSLINES'),
    (N'ledger_lines', N'LG_003_01_CLFLINE', N'dbo.LG_003_01_CLFLINE'),
    (N'paytrans', N'LG_003_01_PAYTRANS', N'dbo.LG_003_01_PAYTRANS'),
    (N'warehouses', N'LG_003_INVDEF', N'dbo.LG_003_INVDEF'),
    (N'itemsubs', N'LG_003_ITEMSUBS', N'dbo.LG_003_ITEMSUBS'),
    (N'firm_docs_002', N'LG_002_FIRMDOC', N'dbo.LG_002_FIRMDOC'),
    (N'firm_docs_003', N'LG_003_FIRMDOC', N'dbo.LG_003_FIRMDOC'),
    (N'custom_raf_candidates', N'LG_XT1001_003', N'dbo.LG_XT1001_003');

DECLARE @Counts TABLE (
    label NVARCHAR(64) NOT NULL,
    full_name NVARCHAR(256) NOT NULL,
    exists_flag BIT NOT NULL,
    row_count BIGINT NULL
);

DECLARE @Label NVARCHAR(64);
DECLARE @FullName NVARCHAR(256);
DECLARE @Sql NVARCHAR(MAX);

DECLARE table_cursor CURSOR LOCAL FAST_FORWARD FOR
    SELECT label, full_name FROM @Tables;

OPEN table_cursor;
FETCH NEXT FROM table_cursor INTO @Label, @FullName;

WHILE @@FETCH_STATUS = 0
BEGIN
    IF OBJECT_ID(@FullName, N'U') IS NOT NULL OR OBJECT_ID(@FullName, N'V') IS NOT NULL
    BEGIN
        SET @Sql = N'SELECT @CountOut = COUNT_BIG(*) FROM ' + @FullName + N';';
        DECLARE @RowCount BIGINT;
        EXEC sp_executesql @Sql, N'@CountOut BIGINT OUTPUT', @CountOut = @RowCount OUTPUT;

        INSERT INTO @Counts (label, full_name, exists_flag, row_count)
        VALUES (@Label, @FullName, 1, @RowCount);
    END
    ELSE
    BEGIN
        INSERT INTO @Counts (label, full_name, exists_flag, row_count)
        VALUES (@Label, @FullName, 0, NULL);
    END

    FETCH NEXT FROM table_cursor INTO @Label, @FullName;
END

CLOSE table_cursor;
DEALLOCATE table_cursor;

SELECT * FROM @Counts ORDER BY label;

PRINT '== Sample product card ==';
SELECT TOP 10
    LOGICALREF,
    CODE,
    NAME,
    NAME2,
    UNITSETREF,
    SPECODE,
    SPECODE2,
    SPECODE3,
    SPECODE4,
    SPECODE5,
    CYPHCODE,
    ACTIVE,
    CARDTYPE,
    CAPIBLOCK_MODIFIEDDATE
FROM dbo.LG_003_ITEMS
WHERE CODE = @SampleSku
   OR CODE LIKE N'%' + @SampleSku + N'%'
ORDER BY LOGICALREF DESC;

PRINT '== Sample product stock by warehouse ==';
IF OBJECT_ID(N'dbo.LV_003_01_STINVTOT', N'V') IS NOT NULL
BEGIN
    SELECT
        s.STOCKREF,
        i.CODE,
        i.NAME,
        s.INVENNO,
        inv.NAME AS warehouse_name,
        SUM(COALESCE(s.ONHAND, 0)) AS onhand_total
    FROM dbo.LV_003_01_STINVTOT s
    INNER JOIN dbo.LG_003_ITEMS i ON i.LOGICALREF = s.STOCKREF
    LEFT JOIN dbo.LG_003_INVDEF inv ON inv.INVENNO = s.INVENNO
    WHERE i.CODE = @SampleSku
    GROUP BY s.STOCKREF, i.CODE, i.NAME, s.INVENNO, inv.NAME
    ORDER BY s.INVENNO;
END
ELSE
BEGIN
    SELECT N'dbo.LV_003_01_STINVTOT not found' AS warning;
END

PRINT '== Warehouse definitions ==';
IF OBJECT_ID(N'dbo.LG_003_INVDEF', N'U') IS NOT NULL
BEGIN
    SELECT TOP 100 *
    FROM dbo.LG_003_INVDEF
    ORDER BY INVENNO;
END
ELSE
BEGIN
    SELECT N'dbo.LG_003_INVDEF not found' AS warning;
END

PRINT '== Product image/document table candidates ==';
IF OBJECT_ID(N'dbo.LG_002_FIRMDOC', N'U') IS NOT NULL
BEGIN
    SELECT TOP 20 *
    FROM dbo.LG_002_FIRMDOC
    ORDER BY LOGICALREF DESC;
END

IF OBJECT_ID(N'dbo.LG_003_FIRMDOC', N'U') IS NOT NULL
BEGIN
    SELECT TOP 20 *
    FROM dbo.LG_003_FIRMDOC
    ORDER BY LOGICALREF DESC;
END

PRINT '== Recent Logo documents for write mapping ==';
IF OBJECT_ID(N'dbo.LG_003_01_ORFICHE', N'U') IS NOT NULL
BEGIN
    SELECT TOP 20 LOGICALREF, FICHENO, DATE_, TRCODE, CLIENTREF, SOURCEINDEX, BRANCH, DEPARTMENT, NETTOTAL, GENEXP1, GENEXP2, SPECODE, CYPHCODE, CANCELLED
    FROM dbo.LG_003_01_ORFICHE
    ORDER BY LOGICALREF DESC;
END

IF OBJECT_ID(N'dbo.LG_003_01_STFICHE', N'U') IS NOT NULL
BEGIN
    SELECT TOP 20 LOGICALREF, FICHENO, DATE_, TRCODE, CLIENTREF, SOURCEINDEX, BRANCH, DEPARTMENT, NETTOTAL, GENEXP1, GENEXP2, SPECODE, CYPHCODE, CANCELLED
    FROM dbo.LG_003_01_STFICHE
    ORDER BY LOGICALREF DESC;
END

IF OBJECT_ID(N'dbo.LG_003_01_INVOICE', N'U') IS NOT NULL
BEGIN
    SELECT TOP 20 LOGICALREF, FICHENO, DATE_, TRCODE, CLIENTREF, SOURCEINDEX, BRANCH, DEPARTMENT, NETTOTAL, GENEXP1, GENEXP2, SPECODE, CYPHCODE, CANCELLED
    FROM dbo.LG_003_01_INVOICE
    ORDER BY LOGICALREF DESC;
END

IF OBJECT_ID(N'dbo.LG_003_01_KSLINES', N'U') IS NOT NULL
BEGIN
    SELECT TOP 20 LOGICALREF, DATE_, CARDREF, CLIENTREF, AMOUNT, TRCODE, FICHENO, LINEEXP, SPECODE, CANCELLED
    FROM dbo.LG_003_01_KSLINES
    ORDER BY LOGICALREF DESC;
END

PRINT '== Powersa B2B procedure status ==';
SELECT
    p.name AS procedure_name,
    p.create_date,
    p.modify_date
FROM sys.procedures p
WHERE p.name LIKE N'PowersaB2B_%'
ORDER BY p.name;

SELECT
    OBJECT_SCHEMA_NAME(p.object_id) + N'.' + OBJECT_NAME(p.object_id) AS procedure_name,
    p.parameter_id,
    p.name AS parameter_name,
    TYPE_NAME(p.user_type_id) AS type_name,
    p.max_length,
    p.precision,
    p.scale,
    p.is_output
FROM sys.parameters p
WHERE OBJECT_NAME(p.object_id) LIKE N'PowersaB2B_%'
ORDER BY procedure_name, p.parameter_id;

PRINT '== Powersa B2B export log ==';
IF OBJECT_ID(N'dbo.POWERSA_B2B_EXPORT_LOG', N'U') IS NOT NULL
BEGIN
    SELECT DOCUMENT_TYPE, STATUS, COUNT(*) AS total, MAX(UPDATED_AT) AS last_updated_at
    FROM dbo.POWERSA_B2B_EXPORT_LOG
    GROUP BY DOCUMENT_TYPE, STATUS
    ORDER BY DOCUMENT_TYPE, STATUS;

    SELECT TOP 50 ID, EXPORT_KEY, DOCUMENT_TYPE, EXTERNAL_REF, STATUS, ERROR_MESSAGE, UPDATED_AT
    FROM dbo.POWERSA_B2B_EXPORT_LOG
    ORDER BY ID DESC;
END
ELSE
BEGIN
    SELECT N'dbo.POWERSA_B2B_EXPORT_LOG not found' AS warning;
END
