<?php
namespace App\Controllers;
use App\Domain\ValidationException;
use App\Services\Audit;
use App\Services\ClientService;
use App\Services\ClientInsightsService;
use App\Services\CustomerPortalService;
use App\Services\StatementService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
final class Clients extends BaseController
{
    public function index(): string
    {
        return $this->renderDirectory();
    }

    private function renderDirectory(?array $clientModal = null): string
    {
        $q=mb_substr(is_string($this->request->getGet('q'))?$this->request->getGet('q'):'',0,100);
        $page=max(1,min(100000,(int)$this->request->getGet('page')));
        $query=db_connect()->table('clientes c');
        if ($q!=='') {$query->groupStart()->like('c.nombre',$q)->orWhereIn('c.id',db_connect()->table('cliente_identificaciones')->select('cliente_id')->like('numero_normalizado',$q))->groupEnd();}
        $status=$this->request->getGet('estado') ?? '';
        if (in_array($status,['0','1'],true)) {$query->where('c.activo',(int)$status);}
        $total=$query->countAllResults(false); $page=min($page,max(1,(int)ceil($total/25)));
        $rows=$query->select('c.*')->select('(SELECT numero_normalizado FROM cliente_identificaciones WHERE cliente_id=c.id ORDER BY tipo LIMIT 1) AS identificacion',false)->orderBy('c.nombre')->orderBy('c.id')->limit(25,($page-1)*25)->get()->getResultArray();
        return $this->renderPage('clients/index', [
            'title' => 'Clientes',
            'description' => 'Información de contacto, identificación y estado.',
            'pagePattern' => 'list',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'q' => $q,
            'status' => $status,
            'canCreate' => \App\Services\Access::can((int) auth()->id(), 'clientes.crear'),
            'canEdit' => \App\Services\Access::can((int) auth()->id(), 'clientes.editar'),
            'clientModal' => $clientModal,
        ]);
    }
    public function form(?string $id=null): string
    {
        $client = $id ? (new ClientService())->find((int) $id) : [];
        return $this->renderDirectory([
            'title' => $id ? 'Editar cliente' : 'Nuevo cliente',
            'description' => 'Completa lo que conoces. La identificación y el contacto son opcionales.',
            'client' => $client,
            'autoOpen' => true,
            'canChangeState' => $id && \App\Services\Access::can((int) auth()->id(), 'clientes.desactivar'),
        ]);
    }
    public function record(string $id): string
    {
        $canInvite = \App\Services\Access::can((int) auth()->id(), 'usuarios.editar');
        return $this->renderPage('clients/record', (new ClientInsightsService())->record((int) $id) + [
            'title' => 'Expediente del cliente',
            'description' => 'Identidad, contacto y actividad registrada en una sola vista.',
            'pagePattern' => 'detail',
            'breadcrumbs' => [
                ['label' => 'Clientes', 'url' => site_url('clientes')],
                ['label' => 'Expediente'],
            ],
            'canEdit' => \App\Services\Access::can((int) auth()->id(), 'clientes.editar'),
            'canInvitePortal' => $canInvite,
            'portalCandidates' => $canInvite ? (new CustomerPortalService())->invitationCandidates() : [],
            'portalInvitationUrl' => session('portal_invitation_url'),
        ]);
    }

    public function portalInvitation(string $id)
    {
        try {
            $token = (new CustomerPortalService())->createInvitation(
                (int) auth()->id(),
                (int) $id,
                (int) $this->request->getPost('usuario_id')
            );

            return redirect()->to(site_url('clientes/' . (int) $id . '/expediente'))
                ->with('message', 'Invitación creada. Copia el enlace y entrégalo únicamente al usuario seleccionado.')
                ->with('portal_invitation_url', site_url('portal/invitacion/' . $token));
        } catch (ValidationException $error) {
            return redirect()->to(site_url('clientes/' . (int) $id . '/expediente'))
                ->withInput()->with('errors', $error->errors);
        }
    }
    public function duplicates(string $id): string
    {
        return $this->renderPage('clients/duplicates', (new ClientInsightsService())->duplicates((int) $id) + [
            'title' => 'Posibles duplicados',
            'description' => 'Compara coincidencias antes de crear o modificar otro registro.',
            'pagePattern' => 'list',
            'canEdit' => \App\Services\Access::can((int) auth()->id(), 'clientes.editar'),
            'breadcrumbs' => [
                ['label' => 'Clientes', 'url' => site_url('clientes')],
                ['label' => 'Expediente', 'url' => site_url('clientes/' . $id . '/expediente')],
                ['label' => 'Posibles duplicados'],
            ],
        ]);
    }

    public function discardDuplicate(string $id)
    {
        \App\Services\Access::require((int) auth()->id(), 'clientes.editar');
        $candidateId = (int) ($this->request->getPost('candidate_id') ?? 0);
        if ($candidateId > 0) {
            Audit::record((int) auth()->id(), 'clientes.descartar_duplicado', 'cliente:' . $id, ['candidate_id' => $candidateId]);
            return redirect()->to(site_url('clientes/' . $id . '/duplicados'))->with('message', 'Comparación marcada como revisión manual.');
        }
        return redirect()->to(site_url('clientes/' . $id . '/duplicados'))->with('error', 'Falta el candidato a descartar.');
    }

    public function credit(string $id): string
    {
        return $this->renderPage('clients/credit', [
            'title' => 'Ventas a crédito',
            'description' => 'Registra ventas a crédito con un plazo definido y da seguimiento a sus cuotas.',
            'pagePattern' => 'form',
            'breadcrumbs' => [
                ['label' => 'Clientes', 'url' => site_url('clientes')],
                ['label' => 'Expediente', 'url' => site_url('clientes/' . $id . '/expediente')],
                ['label' => 'Ventas a crédito'],
            ],
            'client' => (new ClientService())->find((int) $id),
            'policy' => [
                'limit' => '',
                'blocked' => false,
                'graceDays' => '',
                'dueDays' => '',
                'notes' => '',
            ],
        ]);
    }

    public function saveCredit(string $id)
    {
        (new ClientService())->find((int) $id);
        return redirect()->to(site_url('clientes/' . $id . '/credito'))->with('error', 'Las condiciones de crédito aún no pueden guardarse. No se realizó ningún cambio.');
    }

    public function statement(string $id): string
    {
        $client = (new ClientService())->find((int) $id);
        $statement = (new StatementService())->forClient((int) $id, $this->request->getGet());

        return $this->renderPage('clients/statement', $statement + [
            'title' => 'Estado de cuenta del cliente',
            'description' => 'Cargos, cobros y correcciones con saldo acumulado verificable.',
            'pagePattern' => 'detail',
            'breadcrumbs' => [
                ['label' => 'Clientes', 'url' => site_url('clientes')],
                ['label' => 'Expediente', 'url' => site_url('clientes/' . $id . '/expediente')],
                ['label' => 'Estado de cuenta'],
            ],
            'clientId' => (int) $id,
            'client' => $client,
        ]);
    }

    public function data()
    {
        $request = $this->request->getGet();
        $result = array_key_exists('draw', $request)
            ? (new ClientInsightsService())->datatable($request)
            : (new ClientInsightsService())->paginate([
                'q' => $request['q'] ?? null,
                'estado' => $request['estado'] ?? null,
                'page' => $request['page'] ?? null,
            ]);

        return $this->response->setJSON($result);
    }

    public function exportCsv()
    {
        $rows = (new ClientInsightsService())->exportRows($this->request->getGet());
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'No se pudo preparar la exportación.']);
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['ID', 'Cliente', 'Identificación', 'Estado']);
        foreach ($rows as $row) {
            fputcsv($stream, [
                (string) $row['id'],
                $this->safeExportText($row['nombre']),
                $this->safeExportText($row['identificacion']),
                $row['activo'] ? 'Activo' : 'Inactivo',
            ]);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        Audit::record((int) auth()->id(), 'clientes.exportar_csv', 'clientes', ['registros' => count($rows)]);
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="clientes.csv"')
            ->setBody($content === false ? '' : $content);
    }

    public function exportXlsx()
    {
        $rows = (new ClientInsightsService())->exportRows($this->request->getGet());
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clientes');
        $sheet->fromArray(['ID', 'Cliente', 'Identificación', 'Estado'], null, 'A1');

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $sheet->setCellValueExplicit('A' . $line, (string) $row['id'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $line, $this->safeExportText($row['nombre']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $line, $this->safeExportText($row['identificacion']), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $line, $row['activo'] ? 'Activo' : 'Inactivo', DataType::TYPE_STRING);
        }
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $temporaryFile = tempnam(WRITEPATH, 'clientes-export-');
        if ($temporaryFile === false) {
            return $this->response->setStatusCode(500)->setJSON(['error' => 'No se pudo preparar la exportación.']);
        }
        try {
            (new Xlsx($spreadsheet))->save($temporaryFile);
            $content = file_get_contents($temporaryFile);
        } finally {
            @unlink($temporaryFile);
            $spreadsheet->disconnectWorksheets();
        }

        Audit::record((int) auth()->id(), 'clientes.exportar_xlsx', 'clientes', ['registros' => count($rows)]);
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="clientes.xlsx"')
            ->setBody($content === false ? '' : $content);
    }

    private function safeExportText(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

    public function save(?string $id=null)
    {
        try {
            (new ClientService())->save((int)auth()->id(),$this->request->getPost(),$id?(int)$id:null);
            return redirect()->to(site_url('clientes'))->with('message',$id?'Cliente actualizado.':'Cliente registrado.');
        } catch (ValidationException $e) {return redirect()->to(site_url($id?'clientes/'.$id:'clientes/nuevo'))->withInput()->with('errors',$e->errors);}
    }
    public function state(string $id)
    {
        (new ClientService())->setActive((int)auth()->id(),(int)$id,$this->request->getPost('activo')==='1');
        return redirect()->to(site_url('clientes'))->with('message','Estado actualizado. El historial se conserva.');
    }
}
