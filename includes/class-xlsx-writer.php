<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFU_SJT_XLSX_Writer {

	private array $headers = [];
	private array $rows    = [];
	private array $types   = [];

	public function set_headers( array $headers, array $types = [] ) {
		$this->headers = $headers;
		$this->types   = $types;
	}

	public function add_row( array $row ) {
		$this->rows[] = $row;
	}

	public function send( string $filename ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->send_csv( str_replace( '.xlsx', '.csv', $filename ) );
			return;
		}

		$tmp = tempnam( sys_get_temp_dir(), 'efuxlsx' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE );

		$shared  = [];
		$str_map = [];

		$si = function ( $val ) use ( &$shared, &$str_map ) {
			$k = (string) $val;
			if ( ! array_key_exists( $k, $str_map ) ) {
				$str_map[ $k ] = count( $shared );
				$shared[]      = $k;
			}
			return $str_map[ $k ];
		};

		// ── Worksheet XML ─────────────────────────────────────────────────────────
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
			. '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
			. '</sheetView></sheetViews>'
			. '<sheetData>';

		// Header row — style index 1 (bold)
		$sheet .= '<row r="1">';
		foreach ( $this->headers as $ci => $h ) {
			$cl    = $this->col_letter( $ci + 1 );
			$sheet .= '<c r="' . $cl . '1" t="s" s="1"><v>' . $si( $h ) . '</v></c>';
		}
		$sheet .= '</row>';

		// Data rows
		foreach ( $this->rows as $ri => $row ) {
			$er    = $ri + 2;
			$sheet .= '<row r="' . $er . '">';
			foreach ( $row as $ci => $cell ) {
				$cl   = $this->col_letter( $ci + 1 );
				$type = $this->types[ $ci ] ?? 'string';
				if ( $type === 'decimal' && is_numeric( $cell ) ) {
					// s="2" → 0.00 number format style
					$sheet .= '<c r="' . $cl . $er . '" s="2"><v>' . esc_html( $cell ) . '</v></c>';
				} elseif ( $type === 'number' && is_numeric( $cell ) ) {
					$sheet .= '<c r="' . $cl . $er . '"><v>' . esc_html( $cell ) . '</v></c>';
				} else {
					$sheet .= '<c r="' . $cl . $er . '" t="s"><v>' . $si( (string) $cell ) . '</v></c>';
				}
			}
			$sheet .= '</row>';
		}
		$sheet .= '</sheetData></worksheet>';

		// ── Shared strings ────────────────────────────────────────────────────────
		$cnt = count( $shared );
		$ss  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' count="' . $cnt . '" uniqueCount="' . $cnt . '">';
		foreach ( $shared as $str ) {
			$ss .= '<si><t xml:space="preserve">' . htmlspecialchars( $str, ENT_XML1, 'UTF-8' ) . '</t></si>';
		}
		$ss .= '</sst>';

		// ── Styles (two cell formats: normal + bold header) ───────────────────────
		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="3">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
			. '</cellXfs>'
			. '</styleSheet>';

		// ── Workbook ──────────────────────────────────────────────────────────────
		$wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Submissions" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';

		$wb_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';

		$root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';

		$ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';

		$zip->addFromString( '[Content_Types].xml',        $ct );
		$zip->addFromString( '_rels/.rels',                $root_rels );
		$zip->addFromString( 'xl/workbook.xml',            $wb );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $wb_rels );
		$zip->addFromString( 'xl/worksheets/sheet1.xml',   $sheet );
		$zip->addFromString( 'xl/sharedStrings.xml',       $ss );
		$zip->addFromString( 'xl/styles.xml',              $styles );
		$zip->close();

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		header( 'Cache-Control: max-age=0' );
		readfile( $tmp );
		unlink( $tmp );
		exit;
	}

	private function send_csv( string $filename ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $this->headers );
		foreach ( $this->rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	private function col_letter( int $n ): string {
		$letter = '';
		while ( $n > 0 ) {
			$n--;
			$letter = chr( 65 + ( $n % 26 ) ) . $letter;
			$n      = (int) ( $n / 26 );
		}
		return $letter;
	}
}
