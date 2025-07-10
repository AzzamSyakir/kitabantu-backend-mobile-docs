package models

type ChatPayload struct {
	UserId string `json:"user_id"`
	Event  string `json:"event"`
	Room   string `json:"room"`
	Chat   string `json:"chat"`
	File   string `json:"file"`
}
