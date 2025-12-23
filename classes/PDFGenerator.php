<?php
/**
 * PDFGenerator Class
 * Wrapper for TCPDF or FPDF to generate system reports
 */

// Include TCPDF library (assuming it's in vendor or utils)
// require_once __DIR__ . '/../utils/tcpdf/tcpdf.php';

// Mock class if library is missing for demo purposes
if (!class_exists('TCPDF')) {
    class TCPDF {
        public function __construct() {}
        public function SetCreator($c) {}
        public function SetAuthor($a) {}
        public function SetTitle($t) {}
        public function SetSubject($s) {}
        public function setHeaderData($l, $w, $t, $s) {}
        public function setHeaderFont($f) {}
        public function setFooterFont($f) {}
        public function SetDefaultMonospacedFont($f) {}
        public function SetMargins($l, $t, $r) {}
        public function SetAutoPageBreak($b, $m) {}
        public function SetFont($f, $s, $sz) {}
        public function AddPage() {}
        public function writeHTML($h, $ln, $f, $r, $c, $a) {}
        public function Output($n, $d) {}
    }
}

class PDFGenerator {
    private $pdf;
    
    public function __construct() {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('HSMS Ethiopia');
        $this->pdf->SetAuthor('HSMS System');
        
        // Set default header data
        $this->pdf->setHeaderData('', 0, 'HSMS Ethiopia', "High School Management System\nGenerated: " . date('Y-m-d H:i'));
        
        // Set header and footer fonts
        $this->pdf->setHeaderFont(Array('helvetica', '', 10));
        $this->pdf->setFooterFont(Array('helvetica', '', 8));
        
        // Set margins
        $this->pdf->SetMargins(15, 27, 15);
        $this->pdf->SetAutoPageBreak(TRUE, 25);
        
        // Set font
        $this->pdf->SetFont('helvetica', '', 10);
    }
    
    /**
     * Generate Student Transcript
     */
    public function generateTranscript($student, $grades, $output_type = 'I') {
        $this->pdf->SetTitle('Student Transcript - ' . $student['full_name']);
        $this->pdf->AddPage();
        
        $html = '
        <h1 style="text-align: center;">Official Transcript</h1>
        <hr>
        <table cellpadding="5">
            <tr>
                <td><strong>Student Name:</strong> ' . $student['full_name'] . '</td>
                <td><strong>Student ID:</strong> ' . $student['username'] . '</td>
            </tr>
            <tr>
                <td><strong>Grade Level:</strong> ' . $student['grade_level'] . '</td>
                <td><strong>Academic Year:</strong> ' . date('Y') . '</td>
            </tr>
        </table>
        <br><br>
        <table border="1" cellpadding="5" cellspacing="0">
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <th width="40%">Subject</th>
                <th width="20%">Score</th>
                <th width="20%">Grade</th>
                <th width="20%">Remark</th>
            </tr>';
            
        foreach ($grades as $grade) {
            $letter = $this->getGradeLetter($grade['score']);
            $html .= '
            <tr>
                <td>' . $grade['subject'] . '</td>
                <td>' . $grade['score'] . '</td>
                <td>' . $letter . '</td>
                <td>' . $this->getRemark($letter) . '</td>
            </tr>';
        }
        
        $html .= '</table>
        <br><br>
        <p><strong>GPA:</strong> ' . $this->calculateGPA($grades) . '</p>
        <br><br><br>
        <table cellpadding="5">
            <tr>
                <td width="50%">_________________________<br>Registrar Signature</td>
                <td width="50%" align="right">_________________________<br>Date</td>
            </tr>
        </table>';
        
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'transcript_' . $student['username'] . '.pdf';
        $this->pdf->Output($filename, $output_type);
    }
    
    /**
     * Generate ID Card
     */
    public function generateIDCard($student, $output_type = 'I') {
        // Custom page size for ID card
        // This is a simplified version, real ID cards need precise layout
        $this->pdf->AddPage();
        
        $html = '
        <div style="border: 2px solid #000; width: 300px; height: 180px; padding: 10px;">
            <div style="text-align: center; background-color: #4e73df; color: white; padding: 5px;">
                <strong>HSMS Ethiopia</strong>
            </div>
            <div style="margin-top: 10px;">
                <table width="100%">
                    <tr>
                        <td width="30%">
                            <img src="assets/img/avatar.png" width="60" height="60" border="1">
                        </td>
                        <td width="70%">
                            <strong>' . $student['full_name'] . '</strong><br>
                            ID: ' . $student['username'] . '<br>
                            Grade: ' . $student['grade_level'] . '<br>
                            Role: Student
                        </td>
                    </tr>
                </table>
            </div>
            <div style="text-align: center; font-size: 8px; margin-top: 10px;">
                This card is property of HSMS Ethiopia.<br>
                If found, please return to the school administration.
            </div>
        </div>';
        
        $this->pdf->writeHTML($html, true, false, true, false, '');
        $this->pdf->Output('id_card.pdf', $output_type);
    }
    
    /**
     * Generate Report from HTML content
     */
    public function generateFromHTML($title, $html_content, $filename = 'report.pdf', $output_type = 'I') {
        $this->pdf->SetTitle($title);
        $this->pdf->AddPage();
        $this->pdf->writeHTML($html_content, true, false, true, false, '');
        $this->pdf->Output($filename, $output_type);
    }
    
    // Helpers
    private function getGradeLetter($score) {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
    
    private function getRemark($letter) {
        switch ($letter) {
            case 'A': return 'Excellent';
            case 'B': return 'Very Good';
            case 'C': return 'Good';
            case 'D': return 'Satisfactory';
            default: return 'Fail';
        }
    }
    
    private function calculateGPA($grades) {
        // Simple mock GPA calculation
        $total = 0;
        foreach ($grades as $g) {
            if ($g['score'] >= 90) $total += 4.0;
            elseif ($g['score'] >= 80) $total += 3.0;
            elseif ($g['score'] >= 70) $total += 2.0;
            elseif ($g['score'] >= 60) $total += 1.0;
        }
        return count($grades) > 0 ? number_format($total / count($grades), 2) : 0.0;
    }
}
?>