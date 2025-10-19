<?php
require_once 'tcpdf/tcpdf_main/tcpdf.php';
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->Write(0, 'Test PDF');
$pdf->Output('test.pdf', 'I');
?>