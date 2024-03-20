<?php

namespace NW\WebService\References\Operations\Notification;

use Contractor;
use Employee;
use Exception;
use NotificationEvents;
use ReferencesOperation;
use Seller;
use Status;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    public function doOperation(): array
    {
        $data = $this->getRequest('data');

        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        // По хорошему делать все на валидаторе

        if (!is_array($data)) {
            throw new Exception('Bad data', 400);
        }

        $resellerId = $data['resellerId'];
        $notificationType = $data['notificationType'];

        if (empty($resellerId)) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        if (!is_numeric($resellerId)) {
            $result['notificationClientBySms']['message'] = 'Bad resellerId';
            return $result;
        }

        if (empty($notificationType)) {
            $this->badRequestResponse('Empty notificationType');
        }

        if (!is_numeric($notificationType)) {
            $this->badRequestResponse('Bad notificationType');
        }

        $reseller = Seller::getById($resellerId);
        if (empty($reseller)) {
            $this->badRequestResponse('Seller not found!');
        }

        $client = Contractor::getById((int)$data['clientId']);
        if (
            empty($client)
            || $client->type != Contractor::TYPE_CUSTOMER
            || $client->Seller->id !== $resellerId
        ) {
            $this->badRequestResponse('Client not found');
        }


        if (!$cFullName = $client->getFullName()) {
            $cFullName = $client->name;
        }

        $cr = Employee::getById($data['creatorId']);
        if (empty($cr)) {
            $this->badRequestResponse('Creator not found');
        }

        $et = Employee::getById($data['expertId']);
        if (empty($et)) {
            throw new Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __(
                'NewPositionAdded',
                null,
                $resellerId
            );
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __(
                'PositionStatusHasChanged',
                [
                    'FROM' => Status::getName($data['differences']['from']),
                    'TO' => Status::getName($data['differences']['to']),
                ],
                $resellerId
            );
        }

        // все еще лучше использовать валидатор, который не пропустит неправильные типы переменных
        $templateData = [
            'COMPLAINT_ID' => (int)$data['complaintId'],
            'COMPLAINT_NUMBER' => (string)$data['complaintNumber'],
            'CREATOR_ID' => (int)$data['creatorId'],
            'CREATOR_NAME' => $cr->getFullName(),
            'EXPERT_ID' => (int)$data['expertId'],
            'EXPERT_NAME' => $et->getFullName(),
            'CLIENT_ID' => (int)$data['clientId'],
            'CLIENT_NAME' => $cFullName,
            'CONSUMPTION_ID' => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER' => (string)$data['agreementNumber'],
            'DATE' => (string)$data['date'],
            'DIFFERENCES' => $differences,
        ];

        $this->validateTemplate($templateData);

        $emailFrom = getResellerEmailFrom($resellerId);
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage(
                    [
                        0 => [ // MessageTypes::EMAIL
                            'emailFrom' => $emailFrom,
                            'emailTo' => $email,
                            'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                            'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                        ],
                    ],
                    $resellerId,
                    NotificationEvents::CHANGE_RETURN_STATUS
                );
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage(
                    [
                        0 => [ // MessageTypes::EMAIL
                            'emailFrom' => $emailFrom,
                            'emailTo' => $client->email,
                            'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                            'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                        ],
                    ],
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS,
                    $data['differences']['to']
                );
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send(
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS,
                    $data['differences']['to'],
                    $templateData,
                    $error
                );

                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }

    private function badRequestResponse(string $error, int $code = 400): void
    {
        throw new Exception($error, $code);
    }

    private function validateTemplate(array $templateData): void
    {
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                $this->badRequestResponse("Template Data ($key) is empty!", 500);
            }
        }
    }
}
